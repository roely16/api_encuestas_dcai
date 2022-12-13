<?php 

    header('Access-Control-Allow-Origin: *');
    header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Allow: GET, POST, OPTIONS, PUT, DELETE");

    include $_SERVER['DOCUMENT_ROOT'] . '/apps/api_ingresos/sap/functions.php';

    class  Api extends Rest
    {
        public $dbConn;

		public function __construct(){

			parent::__construct();

			$db = new Db();
			$this->dbConn = $db->connect();

        }

        public function obtenerProcesos(){

            $query = '  SELECT CODAREA, DESCRIPCION, CODAREA AS "value", DESCRIPCION as "text"
                        FROM RH_AREAS
                        ORDER BY CODAREA ASC';

            $stid = oci_parse($this->dbConn, $query);

            if (false === oci_execute($stid)) {

                $err = oci_error($stid);

                $str_error = "Error al obtener los procesos";

                $this->throwError($err["code"], $str_error);

            }

            $procesos = array();

            while ($data = oci_fetch_array($stid, OCI_ASSOC)) {
                
                // Obtener a los técnicos
                $area = $data["CODAREA"];

                $query = "  SELECT CONCAT(NOMBRE, CONCAT(' ', APELLIDO)) AS NOMBRE, NIT
                            FROM RH_EMPLEADOS
                            WHERE CODAREA = '$area'
                            AND USUARIO NOT IN ('PROVISIONAL', 'MUNIGUATE', 'BVIRTUAL')";

                $stid_ = oci_parse($this->dbConn, $query);

                if (false === oci_execute($stid_)) {

                    $err = oci_error($stid_);
    
                    $str_error = "Error al obtener los técnicos";
    
                    $this->throwError($err["code"], $str_error);
    
                }

                $tecnicos = array();

                while ($data_ = oci_fetch_array($stid_, OCI_ASSOC)) {

                    $data_["text"] = $data_["NOMBRE"];
                    $data_["value"] = $data_;

                    $tecnicos [] = $data_;

                }

                $data["TECNICOS"] = $tecnicos;

                // Procesos value y text
                $item = array(
                    "value" => $data,
                    "text" => $data["text"]
                );
               
                $procesos [] = $item;

            }

            $this->returnResponse(SUCCESS_RESPONSE, $procesos);

        }

        public function subirEncuestas(){

            $encuestas = $this->param['encuestas'];

            foreach ($encuestas as &$encuesta) {
                
                $estado = $encuesta["upload"];
                
                if (!$estado) {
                   
                    $fecha = $encuesta["fecha"];
                    $id_proceso = $encuesta["id_proceso"];
                    $id_tecnico = $encuesta["id_tecnico"];

                    $query = "INSERT INTO DCAI_ENCUESTA (FECHA_CREACION, ID_PROCESO, ID_TECNICO, FECHA_PUBLICACION) VALUES (TO_DATE('$fecha', 'DD/MM/YYYY HH24:MI:SS'), '$id_proceso', '$id_tecnico', SYSDATE)";

                    $stid_ = oci_parse($this->dbConn, $query);

                    if (false === oci_execute($stid_)) {

                        $err = oci_error($stid_);
        
                        $str_error = "Error al registrar la encuesta";
        
                        $this->throwError($err["code"], $query);
        
                    }

                    $encuesta["upload"] = true;

                    // Obtener el id de la ultima encuesta

                    $query_last = " SELECT ID
                                    FROM DCAI_ENCUESTA
                                    WHERE ROWNUM = 1
                                    ORDER BY ID DESC";

                    $stid2 = oci_parse($this->dbConn, $query_last);                
                    oci_execute($stid2);

                    $ultima_encuesta = oci_fetch_array($stid2, OCI_ASSOC);

                    $id_ultima_encuesa = $ultima_encuesta["ID"];

                    // Insertar los resultados de la encuesta

                    $respuestas = $encuesta["respuestas"];

                    $id_pregunta = 1;

                    foreach ($respuestas as $respuesta) {
                        
                        $query_insert = "   INSERT INTO DCAI_RESULTADOS_ENCUESTA (ID_ENCUESTA, ID_TIPO_PREGUNTA, RESPUESTA)
                                            VALUES ($id_ultima_encuesa, $id_pregunta, '$respuesta')";

                        $stid_respuesta = oci_parse($this->dbConn, $query_insert);                
                        oci_execute($stid_respuesta);

                        $id_pregunta++;

                    }

                }

            }

            $this->returnResponse(SUCCESS_RESPONSE, $encuestas);

        }

        public function listaEncuestas(){

            $query = "  SELECT T1.ID, TO_CHAR(T1.FECHA_CREACION, 'DD/MM/YYYY HH24:MI:SS') AS FECHA, T2.DESCRIPCION AS PROCESO, 
                        CONCAT(T3.NOMBRE, CONCAT(' ', T3.APELLIDO)) AS TECNICO
                        FROM DCAI_ENCUESTA T1
                        INNER JOIN RH_AREAS T2
                        ON T1.ID_PROCESO = T2.CODAREA
                        INNER JOIN RH_EMPLEADOS T3
                        ON T1.ID_TECNICO = T3.NIT
                        ORDER BY ID DESC";

            $stid = oci_parse($this->dbConn, $query);
            oci_execute($stid);

            $encuestas = [];

            while ($data = oci_fetch_array($stid, OCI_ASSOC)) {
                
                $encuestas [] = $data;

            }

            $this->returnResponse(SUCCESS_RESPONSE, $encuestas);

        }

        public function detallesEncuesta(){

            $id_encuesta = $this->param['id_encuesta'];

            $data = [];

            // Encuesta
            $query = "  SELECT T1.ID, T1.ID_PROCESO, T1.ID_TECNICO, 
                        TO_CHAR(T1.FECHA_CREACION, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_CREACION, T2.DESCRIPCION AS PROCESO, CONCAT(T3.NOMBRE, CONCAT(' ', T3.APELLIDO)) TECNICO
                        FROM DCAI_ENCUESTA T1
                        INNER JOIN RH_AREAS T2
                        ON T1.ID_PROCESO = T2.CODAREA
                        INNER JOIN RH_EMPLEADOS T3
                        ON T1.ID_TECNICO = T3.NIT
                        WHERE ID = $id_encuesta";

            $stid = oci_parse($this->dbConn, $query);
            oci_execute($stid);

            $encuesta = oci_fetch_array($stid, OCI_ASSOC);

            // Procesos 
            $query = '  SELECT CODAREA AS "value", DESCRIPCION as "text"
                        FROM RH_AREAS
                        ORDER BY CODAREA ASC';

            $stid = oci_parse($this->dbConn, $query);
            oci_execute($stid);

            $procesos = [];

            while ($data = oci_fetch_array($stid, OCI_ASSOC)) {
            
                $procesos [] = $data;

            }

            // Respuestas
            $query = "  SELECT *
                        FROM DCAI_RESULTADOS_ENCUESTA
                        WHERE ID_ENCUESTA = $id_encuesta
                        ORDER BY ID_TIPO_PREGUNTA ASC";

            $stid = oci_parse($this->dbConn, $query);
            oci_execute($stid);

            $respuestas = [];        

            while ($data = oci_fetch_array($stid, OCI_ASSOC)) {
                
                $respuestas [] = $data;

            }

            $data["encuesta"] = $encuesta;
            $data["procesos"] = $procesos;
            $data["respuestas"] = $respuestas;
 
            $this->returnResponse(SUCCESS_RESPONSE, $data);

        }

        public function tecnicosProceso(){

            $id_proceso = $this->param['id_proceso'];

            $query = "  SELECT CONCAT(NOMBRE, CONCAT(' ', APELLIDO)) AS NOMBRE, NIT
                        FROM RH_EMPLEADOS
                        WHERE CODAREA = '$id_proceso'
                        AND USUARIO NOT IN ('PROVISIONAL', 'MUNIGUATE', 'BVIRTUAL')";

            $stid = oci_parse($this->dbConn, $query);
            oci_execute($stid);

            $tecnicos = [];

            while ($data = oci_fetch_array($stid, OCI_ASSOC)) {
                
                $data["text"] = $data["NOMBRE"];
                $data["value"] = $data["NIT"];

                $tecnicos [] = $data;

            }

            $this->returnResponse(SUCCESS_RESPONSE, $tecnicos);

        }

        public function editarEncuesta(){

            $encuesta = $this->param['encuesta'];
            $respuestas = $this->param['respuestas'];
            $proceso = $this->param['proceso'];
            $tecnico = $this->param['tecnico'];
            $respuestas = $this->param['respuestas'];

            $id_encuesta = $encuesta["ID"];
            $proceso = $proceso["value"];
            $tecnico = $tecnico["value"];

            // Actualizar la encuesta
            $query = "  UPDATE DCAI_ENCUESTA 
                        SET ID_PROCESO = '$proceso', ID_TECNICO = '$tecnico'
                        WHERE ID = '$id_encuesta'";

            $stid = oci_parse($this->dbConn, $query);
            oci_execute($stid);

            // Actualizar las respuestas
            foreach($respuestas as $respuesta){

                $id = $respuesta["ID"];

                if(array_key_exists("RESPUESTA", $respuesta)){
                    $respuesta_str = $respuesta["RESPUESTA"];
                }else{
                    $respuesta_str = "";
                }

                $query = "  UPDATE DCAI_RESULTADOS_ENCUESTA
                            SET RESPUESTA = '$respuesta_str'
                            WHERE ID = '$id'";

                $stid = oci_parse($this->dbConn, $query);
                oci_execute($stid);

            }

            $this->returnResponse(SUCCESS_RESPONSE, $respuestas);

        }

        public function eliminarEncuesta(){

            $id_encuesta = $this->param['id_encuesta'];

            $query = "DELETE FROM DCAI_ENCUESTA WHERE ID = $id_encuesta";
            $stid = oci_parse($this->dbConn, $query);
            oci_execute($stid);

            $this->returnResponse(SUCCESS_RESPONSE, $id_encuesta);

        }

    }
    

?>