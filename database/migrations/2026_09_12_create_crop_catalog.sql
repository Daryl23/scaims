-- Table creation only. The existing application helper preserves its current
-- behavior of seeding the built-in catalog only when this table is empty.
CREATE TABLE IF NOT EXISTS crop_catalog (
    id INT AUTO_INCREMENT PRIMARY KEY,
    commodity VARCHAR(32) NOT NULL,
    crop_name VARCHAR(120) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_crop_catalog_commodity_name (commodity, crop_name),
    KEY idx_crop_catalog_active (is_active),
    KEY idx_crop_catalog_commodity_sort (commodity, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
