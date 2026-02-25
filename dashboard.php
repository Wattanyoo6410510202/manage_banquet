<?php 
include "header.php"; 

// Mock Data (ในอนาคตเปลี่ยนเป็น SQL Query)
$total_events = 12;
$pending_events = 3;
$revenue = "450,000";
?>

<div class="row g-4 mb-5">
    <div class="col-md-4">
        <div class="card stat-card shadow-sm p-3">
            <div class="d-flex align-items-center">
                <div class="icon-box bg-blue-light me-3">
                    <i class="bi bi-calendar-event"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-0 small uppercase fw-bold">Total Events</h6>
                    <h3 class="fw-bold mb-0"><?php echo $total_events; ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card stat-card shadow-sm p-3">
            <div class="d-flex align-items-center">
                <div class="icon-box bg-gold-light me-3">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-0 small uppercase fw-bold">Pending Approval</h6>
                    <h3 class="fw-bold mb-0"><?php echo $pending_events; ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card stat-card shadow-sm p-3">
            <div class="d-flex align-items-center">
                <div class="icon-box bg-success-light me-3">
                    <i class="bi bi-currency-dollar"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-0 small uppercase fw-bold">Est. Revenue</h6>
                    <h3 class="fw-bold mb-0">฿<?php echo $revenue; ?></h3>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-list-stars me-2 text-primary"></i>Upcoming Functions</h5>
                <a href="manage_banquet.php" class="btn btn-sm btn-link text-decoration-none">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Date</th>
                            <th>Event Name</th>
                            <th>Room</th>
                            <th>Pax</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="ps-4">28 Feb 2026</td>
                            <td><span class="fw-600">Wedding Reception - K.Anan</span></td>
                            <td>Grand Ballroom</td>
                            <td>350</td>
                            <td><span class="badge bg-success-subtle rounded-pill">Confirmed</span></td>
                            <td><button class="btn btn-sm btn-light border shadow-sm"><i class="bi bi-eye"></i></button></td>
                        </tr>
                        <tr>
                            <td class="ps-4">02 Mar 2026</td>
                            <td><span class="fw-600">Annual Seminar (BOT)</span></td>
                            <td>Meeting Room A</td>
                            <td>120</td>
                            <td><span class="badge bg-warning-subtle rounded-pill">Pending</span></td>
                            <td><button class="btn btn-sm btn-light border shadow-sm"><i class="bi bi-eye"></i></button></td>
                        </tr>
                        <tr>
                            <td class="ps-4">05 Mar 2026</td>
                            <td><span class="fw-600">Birthday Party - Private</span></td>
                            <td>Garden Terrace</td>
                            <td>45</td>
                            <td><span class="badge bg-info-subtle rounded-pill">New</span></td>
                            <td><button class="btn btn-sm btn-light border shadow-sm"><i class="bi bi-eye"></i></button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm border-0 bg-sidebar p-4 mb-4">
            <h6 class="fw-bold mb-4 border-bottom pb-2">Quick Actions</h6>
            <div class="d-grid gap-2">
                <a href="index.php" class="btn btn-dark py-2 rounded-3 text-start shadow-sm">
                    <i class="bi bi-plus-circle-fill me-2 text-warning"></i> Create New Function
                </a>
                <button class="btn btn-hotel-outline py-2 rounded-3 text-start bg-white shadow-sm">
                    <i class="bi bi-printer me-2"></i> Print Daily Schedule
                </button>
            </div>
        </div>

        <div class="card shadow-sm border-0 p-4">
            <h6 class="fw-bold mb-3 border-bottom pb-2">Operational Alerts</h6>
            <div class="alert alert-warning border-0 small py-2 shadow-sm">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <strong>Kitchen:</strong> Seafood Allergy #104
            </div>
            <div class="alert alert-info border-0 small py-2 shadow-sm">
                <i class="bi bi-info-circle-fill me-2"></i> <strong>Setup:</strong> VIP Board Room 14:00
            </div>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>