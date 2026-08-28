<?php
$hash = '$2y$10$Uv0V3xJ1E6yJ6bWq9oO8eFvP2T6vF8lV1n8F2E4sHjO7I6iO5WmW';
$candidates = [
    'password', 'password123', 'admin123', 'kamal123', 'secret', 
    '123456', '12345678', 'kesara123', 'kamal', 'john', 'nimali', 
    'aruni', 'user', 'buyer123', 'pass123', 'Password123!', '1234',
    'ABC Garments', 'abc.lk', 'Seylan', 'Fashion'
];

foreach ($candidates as $c) {
    if (password_verify($c, $hash)) {
        echo "MATCH: $c\n";
        exit;
    }
}
echo "NO MATCH FOUND\n";
