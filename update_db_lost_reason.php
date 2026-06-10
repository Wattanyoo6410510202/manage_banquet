<?php
include "includes/db.php";
$sql = "ALTER TABLE quotations ADD COLUMN lost_reason TEXT AFTER remarks";
if ($conn->query($sql)) {
    echo "Successfully added lost_reason column.";
} else {
    echo "Error adding column: " . $conn->error;
}
?>