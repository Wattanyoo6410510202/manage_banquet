<?php
include "../config.php";

header('Content-Type: application/json');

// Fetch sales targets
$query = "SELECT st.*, u.name 
          FROM sales_targets st 
          JOIN users u ON st.user_id = u.id 
          ORDER BY st.target_year DESC, st.target_month DESC";

$result = $conn->query($query);
$targets = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Add month name for convenience
        $row['month_name'] = date('F', mktime(0, 0, 0, $row['target_month'], 1));
        $targets[] = $row;
    }
}

echo json_encode($targets);
?>
