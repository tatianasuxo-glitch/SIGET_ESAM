<?php

/* ======================================================
   CONEXIÓN LOCAL - BASE PRINCIPAL SIGET
====================================================== */

$host_local = "localhost";
$dbname_local = "proyecto_la_paz";
$user_local = "root";
$pass_local = "";

try {
    $conexion = new PDO(
        "mysql:host={$host_local};dbname={$dbname_local};charset=utf8mb4",
        $user_local,
        $pass_local,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("Error de conexión local: " . $e->getMessage());
}


/* ======================================================
   FUNCIÓN PARA BASE LOCAL
   Para módulos nuevos y APIs
====================================================== */

if (!function_exists('siget_local')) {
    function siget_local(): PDO
    {
        global $conexion;

        if (!$conexion instanceof PDO) {
            throw new Exception("La conexión local no está disponible.");
        }

        return $conexion;
    }
}


/* ======================================================
   CONEXIÓN EXTERNA - BASE INSTITUCIONAL
====================================================== */

if (!function_exists('siget_externa')) {
    function siget_externa(): PDO
    {
        $host = "localhost";
        $dbname = "siget_externa";
        $user = "root";
        $pass = "";

        return new PDO(
            "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
    }
}




if (!function_exists('dbLocal')) {
    function dbLocal(): PDO
    {
        return siget_local();
    }
}

if (!function_exists('dbExterna')) {
    function dbExterna(): PDO
    {
        return siget_externa();
    }
}