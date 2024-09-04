<?php 

namespace Paw\Core\Database;

use PDO;
use Monolog\Logger;
use Exception;
use PDOException;

class QueryBuilder 
{
    public PDO $pdo;
    public Logger $logger;
    private $lastQuery;

    public function __construct(PDO $pdo, Logger $logger = null)
    {   
        $this->pdo = $pdo;
        $this->logger = $logger;
    }

    public function select($table, $params = []) {
        try {
            $whereClauses = [];
            $bindings = [];
    
            // Construir las cláusulas WHERE y los parámetros de enlace
            if (isset($params['id'])) {
                $whereClauses[] = "id = :id";
                $bindings[':id'] = $params['id'];
            }
    
            if (isset($params['email'])) {
                $whereClauses[] = "email = :email";
                $bindings[':email'] = $params['email'];
            }        
            
            if (isset($params['id_publicacion'])) {
                $whereClauses[] = "id_publicacion = :id_publicacion";
                $bindings[':id_publicacion'] = $params['id_publicacion'];
            }
    
            if (isset($params['id_imagen'])) {
                $whereClauses[] = "id_imagen = :id_imagen";
                $bindings[':id_imagen'] = $params['id_imagen'];
            }
    
            // Unir las cláusulas WHERE con AND si hay alguna cláusula
            $where = '';
            if (!empty($whereClauses)) {
                $where = ' WHERE ' . implode(' AND ', $whereClauses);
            }
    
            // Construir la consulta SQL
            $query = "SELECT * FROM {$table}{$where}";
    
            // Logging para depuración
            $this->logger->info("Query final antes de preparar: ", [$query]);
            $this->logger->info("Bindings: ", $bindings);
    
            // Preparar la sentencia
            $sentencia = $this->pdo->prepare($query);
    
            // Enlazar los valores de los parámetros
            foreach ($bindings as $key => $value) {
                $this->logger->info("Binding {$key} with value {$value}");
                $sentencia->bindValue($key, $value);
            }
    
            // Establecer el modo de obtención y ejecutar la consulta
            $sentencia->setFetchMode(PDO::FETCH_ASSOC);
            $sentencia->execute();
    
            $resultado = $sentencia->fetchAll();
    
            // Verificar si el resultado es válido
            if ($resultado === false) {
                $this->logger->error('Error al recuperar los datos de la base de datos.');
                throw new Exception('Error al recuperar los datos de la base de datos.');
            } elseif (empty($resultado)) {
                $this->logger->warning('No se encontraron registros en la consulta.');
                return [];
            }
    
            return $resultado;
        } catch (PDOException $e) {
            // Manejar la excepción de la base de datos
            $this->logger->error('Database error: ' . $e->getMessage());
            throw new Exception('Error al realizar la consulta en la base de datos');
        } catch (Exception $e) {
            // Manejar otras excepciones
            $this->logger->error('General error: ' . $e->getMessage());
            throw new Exception('Ocurrió un error inesperado');
        }
    }
    

    public function selectPaginated($table, $params = [], $limit = 20, $offset = 0) {
        try {
            // Configurar PDO para lanzar excepciones en caso de error
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);            
    
            $whereClauses = [];
            $bindings = [];
        
            // Construir las cláusulas WHERE y los parámetros de enlace
            if (isset($params['searchTerm']) && !empty($params['searchTerm'])) {
                $searchTerm = $params['searchTerm'];
                // Buscar en varios campos de la tabla 'agentes' y en la tabla 'estado_agentes'
                $whereClauses[] = "(agentes.credencial LIKE :searchTerm OR
                                    agentes.apellido LIKE :searchTerm OR
                                    agentes.nombre LIKE :searchTerm OR
                                    agentes.cuil LIKE :searchTerm OR
                                    estado_agentes.estado LIKE :searchTerm)";
                $bindings[':searchTerm'] = '%' . $searchTerm . '%';
            }
        
            // Unir las cláusulas WHERE con AND
            $where = implode(' AND ', $whereClauses);
            $query = "
            SELECT agentes.*, estado_agentes.estado as estado
            FROM {$table} AS agentes
            LEFT JOIN estado_agentes ON agentes.estado_id = estado_agentes.id
            " . ($where ? " WHERE {$where}" : "") . " 
            LIMIT :limit OFFSET :offset";
    
            // Logging para depuración
            $this->logger->info("Bindings: ", $bindings);
            $this->logger->info("Query final antes de preparar: ", [$query]);
    
            // Preparar la sentencia
            $sentencia = $this->pdo->prepare($query);
            
            // Enlazar los valores de los parámetros
            foreach ($bindings as $key => $value) {
                $this->logger->info("Binding {$key} with value {$value}");
                $sentencia->bindValue($key, $value);
            }
            
            // Enlazar los parámetros LIMIT y OFFSET
            $this->logger->info("Binding :limit with value {$limit}");
            $sentencia->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $this->logger->info("Binding :offset with value {$offset}");
            $sentencia->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            
            // Establecer el modo de obtención y ejecutar la consulta
            $sentencia->setFetchMode(PDO::FETCH_ASSOC);
            $sentencia->execute();
    
            $resultado = $sentencia->fetchAll();
    
            // Verificar si el resultado es válido
            if ($resultado === false) {
                $this->logger->error('Error al recuperar los datos de la base de datos.');
                throw new Exception('Error al recuperar los datos de la base de datos.');
            } elseif (empty($resultado)) {
                $this->logger->warning('No se encontraron registros en la consulta.');
                return [];
            }
            
            return $resultado;
        } catch (PDOException $e) {
            $this->logger->error('Database error: ' . $e->getMessage());
            throw new Exception('Error al realizar la consulta en la base de datos');
        } catch (Exception $e) {
            $this->logger->error('General error: ' . $e->getMessage());
            throw new Exception('Ocurrió un error inesperado');
        }
    }
            
           


    public function insert($table, $data)
    {
        $columnas = implode(', ', array_keys($data));
        $valores = ':' . implode(', :', array_keys($data));
        $query = "INSERT INTO $table ($columnas) VALUES ($valores)";
        $sentencia = $this->pdo->prepare($query);
    
        // Asignar valores a los parámetros
        foreach ($data as $clave => $valor) {
            $sentencia->bindValue(":$clave", $valor);
        }
    
        $resultado = $sentencia->execute();
        
        $idGenerado = $this->pdo->lastInsertId();

        return [$idGenerado, $resultado];
    }

    public function insertMany($table, $data)
    {
        try {
            $this->logger->info("data en capa DB: ", [$data]);
            
            // Preparar las columnas
            $columns = ['id_publicacion', 'path_imagen', 'nombre_imagen', 'id_usuario'];
            $placeholders = rtrim(str_repeat('(?, ?, ?, ?), ', count($data)), ', '); // Genera los marcadores de posición
    
            // Preparar la consulta de inserción
            $query = "INSERT INTO $table (" . implode(', ', $columns) . ") VALUES $placeholders";
    
            // Preparar los valores para la inserción
            $values = [];
            foreach ($data as $imagen) {
                $values[] = $imagen['id_publicacion'];
                $values[] = $imagen['path_imagen'];
                $values[] = $imagen['nombre_imagen'];
                $values[] = $imagen['id_usuario'];
            }
    
            // Preparar la sentencia
            $statement = $this->pdo->prepare($query);
            
            // Ejecutar la consulta con los valores preparados
            $statement->execute($values);
            
            return true;
        } catch (PDOException $e) {
            // Manejo de la excepción
            $this->logger->error("Error al insertar múltiples imágenes: " . $e->getMessage());
            return false;
        }
    }  

    public function getImagePath($imagesTable, $id_publicacion, $id_imagen)
    {
        try {

            $this->logger->info("imagesTable, id_publicacion, id_imagen ",[$imagesTable, $id_publicacion, $id_imagen]);
            $sql = "
                SELECT path_imagen
                FROM $imagesTable
                WHERE id_publicacion = :id_publicacion AND id_imagen = :id_imagen;
            ";
            
            $stmt = $this->pdo->prepare($sql);
    
            $this->logger->info("stmt: ", [$stmt]);

            $stmt->bindValue(':id_publicacion', $id_publicacion, PDO::PARAM_INT);
            $stmt->bindValue(':id_imagen', $id_imagen, PDO::PARAM_INT);
        
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            // Registrar el éxito utilizando el logger

            if ($result) {
                $this->logger->info("path_imagen recuperada: ", [$result]);
                return $result;
            } else {
                // Si no se encuentra la imagen, devuelve false
                $this->logger->info("No se encontró ninguna imagen con los IDs proporcionados.", [$result]);
                return false;
            }
        } catch (PDOException $e) {
            // Manejo de la excepción
            // Registrar el error utilizando el logger
            $this->logger->error("Error al ejecutar la consulta: " . $e->getMessage());
            // Puedes lanzar una excepción personalizada aquí o manejarla de otra manera según tus necesidades
            return false;
        }
    }    

    public function getAllWithImages($mainTable, $imageTable, $mainTableKey, $foreignKey) {
        try {
            $query = "
                SELECT 
                    main.*, 
                    img.id_imagen, 
                    img.path_imagen, 
                    img.nombre_imagen 
                FROM 
                    {$mainTable} main
                LEFT JOIN 
                    {$imageTable} img
                ON 
                    main.{$mainTableKey} = img.{$foreignKey}
            ";

            $statement = $this->pdo->prepare($query);
            $statement->execute();
            return $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Logging the error
            $this->logger->error("Error in getAllWithImages: " . $e->getMessage());
            return false;
        }
    }    
    

    public function getAllWithImagesByUser($mainTable, $imageTable, $mainTableKey, $foreignKey, $idUser) {
        try {

            $this->logger->info("parametors: ", [$mainTable, $imageTable, $mainTableKey, $foreignKey, $idUser]);
            $query = "
                SELECT 
                    main.*, 
                    img.id_imagen, 
                    img.path_imagen, 
                    img.nombre_imagen 
                FROM 
                    {$mainTable} main
                LEFT JOIN 
                    {$imageTable} img
                ON 
                    main.{$mainTableKey} = img.{$foreignKey}
                WHERE
                    main.id_usuario = :id_usuario
            ";
    
            $statement = $this->pdo->prepare($query);
            $statement->bindParam(':id_usuario', $idUser, PDO::PARAM_INT);
            $statement->execute();
            return $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Logging the error
            $this->logger->error("Error in getAllWithImagesByUser: " . $e->getMessage());
            return false;
        }
    }

    public function getLastQuery()
    {
        return $this->lastQuery;
    }


    public function update()
    {

    }

    public function delete()
    {

    }
}