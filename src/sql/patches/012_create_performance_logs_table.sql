CREATE TABLE IF NOT EXISTS performance_logs (
    performance_log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    type VARCHAR(50) NOT NULL,
    target TEXT NOT NULL,
    duration FLOAT NOT NULL,
    context JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
