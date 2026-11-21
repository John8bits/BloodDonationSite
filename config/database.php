<?php
class Database
{

    private $host = "localhost";
    private $user = "root";
    private $password = "";
    private $dbname = "blood_donate";

    public $conn;

    public function __construct()
    {

        /*try{
            $this->conn = new PDO("mysql:host=".$this->host . ";dbname=".$this->dbname,
            $this->user,$this->password);

            $this->conn->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
            #echo "Connection success";
        }catch(PDOException $e){
            die("Connection Failed ".$e->getMessage());
        }*/

        try {
            $databaseName = getenv('BLIFE_DB_NAME');
            $databaseName = $databaseName !== false && $databaseName !== '' ? $databaseName : $this->dbname;
            if (!preg_match('/\A[A-Za-z0-9_]+\z/', $databaseName)) {
                throw new InvalidArgumentException('BLIFE_DB_NAME contains invalid characters.');
            }
            $dsn = "mysql:host={$this->host};dbname={$databaseName};charset=utf8mb4";
            $this->conn = new PDO(
                $dsn,
                $this->user,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );

        } catch (PDOException $e) {

            error_log($e->getMessage());

            die("Database connection failed.");
        }

    }

    /* public function getConnection()
     {
         return $this->conn;
     }*/
    public function getConnection(): PDO
    {
        return $this->conn;
    }

}

?>
