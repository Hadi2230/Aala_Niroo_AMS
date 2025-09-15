<?php
session_start();
if (!isset($_SESSION['user_id'])) {
	header('Location: login.php');
	exit();
}

include 'config.php';

if (!headers_sent()) {
	header('Content-Type: text/html; charset=utf-8');
}

$customers   = [];
$assets      = [];
$search_term = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
	$search_term = trim($_POST['search_term'] ?? '');
	if ($search_term !== '') {
		$like = "%{$search_term}%";

		// جستجوی مشتریان: بر اساس full_name / company و شماره‌های مختلف
		try {
			$sql = "
				SELECT
					id,
					CASE
						WHEN customer_type='حقوقی' AND COALESCE(company,'')<>'' THEN company
						WHEN COALESCE(full_name,'')<>'' THEN full_name
						ELSE COALESCE(company, full_name, CONCAT('مشتری ', id))
					END AS display_name,
					COALESCE(NULLIF(phone,''), NULLIF(company_phone,''), NULLIF(responsible_phone,'')) AS primary_phone,
					address
				FROM customers
				WHERE
					COALESCE(phone,'') LIKE ?
					OR COALESCE(company_phone,'') LIKE ?
					OR COALESCE(responsible_phone,'') LIKE ?
					OR COALESCE(full_name,'') LIKE ?
					OR COALESCE(company,'') LIKE ?
				ORDER BY display_name
			";
			$stmt = $pdo->prepare($sql);
			$stmt->execute([$like, $like, $like, $like, $like]);
			$customers = $stmt->fetchAll();
		} catch (Throwable $e) {
			error_log('survey_customer_search customers error: ' . $e->getMessage());
			$customers = [];
		}

		// جستجوی دستگاه‌ها: نام / سریال‌ها / مدل
		try {
			$sql = "
				SELECT id, name, serial_number, model, device_serial
				FROM assets
				WHERE
					COALESCE(serial_number,'')   LIKE ?
					OR COALESCE(device_serial,'') LIKE ?
					OR COALESCE(name,'')          LIKE ?
					OR COALESCE(model,'')         LIKE ?
				ORDER BY name
			";
			$stmt = $pdo->prepare($sql);
			$stmt->execute([$like, $like, $like, $like]);
			$assets = $stmt->fetchAll();
		} catch (Throwable $e) {
			error_log('survey_customer_search assets error: ' . $e->getMessage());
			$assets = [];
		}
	}
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>جستجو برای نظرسنجی - اعلا نیرو</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
	<link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
	<style>
		body { font-family: Vazirmatn, sans-serif; background-color: #f8f9fa; padding-top: 80px; }
		.card { margin-bottom: 20px; border: none; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
		.card-header { background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); color: white; border-radius: 10px 10px 0 0 !important; }
	</style>
</head>
<body>
	<?php include 'navbar.php'; ?>

	<div class="container mt-4">
		<h2 class="text-center mb-4">جستجو برای شروع نظرسنجی</h2>

		<div class="card mb-4">
			<div class="card-header">جستجوی مشتری یا دستگاه</div>
			<div class="card-body">
				<form method="POST">
					<div class="input-group mb-3">
						<input type="text" class="form-control" placeholder="شماره تماس مشتری، نام مشتری/شرکت، یا شماره سریال دستگاه" name="search_term" value="<?php echo htmlspecialchars($search_term); ?>">
						<button class="btn btn-primary" type="submit" name="search">جستجو</button>
					</div>
				</form>
			</div>
		</div>

		<?php if ($search_term !== ''): ?>
			<?php if (count($customers) > 0): ?>
				<div class="card mb-4">
					<div class="card-header">نتایج جستجوی مشتریان</div>
					<div class="card-body">
						<div class="table-responsive">
							<table class="table table-striped">
								<thead>
									<tr>
										<th>نام/عنوان</th>
										<th>تلفن</th>
										<th>آدرس</th>
										<th>عملیات</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($customers as $customer): ?>
										<tr>
											<td><?php echo htmlspecialchars($customer['display_name'] ?? '-'); ?></td>
											<td><?php echo htmlspecialchars($customer['primary_phone'] ?? '-'); ?></td>
											<td><?php echo htmlspecialchars($customer['address'] ?? '-'); ?></td>
											<td>
												<a href="survey.php?customer_id=<?php echo (int)$customer['id']; ?>" class="btn btn-primary btn-sm">شروع نظرسنجی</a>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			<?php endif; ?>

			<?php if (count($assets) > 0): ?>
				<div class="card mb-4">
					<div class="card-header">نتایج جستجوی دستگاه‌ها</div>
					<div class="card-body">
						<div class="table-responsive">
							<table class="table table-striped">
								<thead>
									<tr>
										<th>نام دستگاه</th>
										<th>شماره سریال</th>
										<th>سریال دستگاه</th>
										<th>مدل</th>
										<th>عملیات</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($assets as $asset): ?>
										<tr>
											<td><?php echo htmlspecialchars($asset['name'] ?? '-'); ?></td>
											<td><?php echo htmlspecialchars($asset['serial_number'] ?? '-'); ?></td>
											<td><?php echo htmlspecialchars($asset['device_serial'] ?? '-'); ?></td>
											<td><?php echo htmlspecialchars($asset['model'] ?? '-'); ?></td>
											<td>
												<a href="survey_response.php?asset_id=<?php echo (int)$asset['id']; ?>" class="btn btn-primary btn-sm">شروع نظرسنجی</a>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			<?php endif; ?>

			<?php if (count($customers) === 0 && count($assets) === 0): ?>
				<div class="alert alert-warning">هیچ نتیجه‌ای یافت نشد.</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>