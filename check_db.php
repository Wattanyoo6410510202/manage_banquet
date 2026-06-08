<?php
include "includes/db.php";
$res = $conn->query("DESCRIBE quotations");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
?>