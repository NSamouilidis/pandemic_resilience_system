SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


CREATE TABLE `access_logs` (
  `log_id` int(11) NOT NULL,
  `prs_id` varchar(20) NOT NULL,
  `access_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `action` varchar(100) NOT NULL,
  `resource_type` varchar(50) NOT NULL,
  `resource_id` varchar(50) NOT NULL,
  `status` enum('success','failed') NOT NULL,
  `ip_address` varchar(45) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



INSERT INTO `access_logs` (`log_id`, `prs_id`, `access_time`, `action`, `resource_type`, `resource_id`, `status`, `ip_address`) VALUES
(0, 'PRS-PUB-002', '2025-05-06 14:09:36', 'view', 'profile', 'public', 'success', '::1'),
(0, 'PRS-PUB-002', '2025-05-06 14:09:50', 'view', 'profile', 'public', 'success', '::1'),
(0, 'PRS-PUB-002', '2025-05-06 14:09:52', 'view', 'profile', 'public', 'success', '::1'),
(0, 'PRS-PUB-002', '2025-05-06 14:10:04', 'view', 'profile', 'public', 'success', '::1'),
(0, 'PRS-PUB-002', '2025-05-06 14:10:07', 'view', 'profile', 'public', 'success', '::1'),
(0, 'PRS-PUB-002', '2025-05-06 14:10:07', 'view', 'resource_finder', 'page', 'success', '::1'),
(0, 'PRS-PUB-002', '2025-05-06 14:10:10', 'view', 'purchase_history', 'page', 'success', '::1'),
(0, 'PRS-PUB-002', '2025-05-06 14:10:49', 'view', 'dashboard', 'public', 'success', '::1'),
(0, 'PRS-PUB-002', '2025-05-06 14:10:52', 'view', 'vaccinations', 'page', 'success', '::1'),
(0, 'PRS-PUB-002', '2025-05-06 14:10:53', 'view', 'dashboard', 'public', 'success', '::1'),
(0, 'PRS-GOV-001', '2025-05-07 13:18:29', 'login', 'system', 'auth', 'success', '::1'),
(0, 'PRS-GOV-001', '2025-05-07 13:18:29', 'view', 'dashboard', 'government', 'success', '::1'),
(0, 'PRS-GOV-001', '2025-05-07 13:18:32', 'view', 'users', 'page', 'success', '::1'),
(0, 'PRS-GOV-001', '2025-05-07 13:18:35', 'view', 'inventory', 'page', 'success', '::1'),
(0, 'PRS-GOV-001', '2025-05-07 13:18:37', 'view', 'merchants', 'page', 'success', '::1'),
(0, 'PRS-GOV-001', '2025-05-07 13:18:40', 'view', 'users', 'page', 'success', '::1'),
(0, 'PRS-GOV-001', '2025-05-07 13:18:45', 'generate', 'report', 'vaccination', 'success', '::1'),
(0, 'PRS-GOV-001', '2025-05-07 13:19:09', 'generate', 'report', 'vaccination', 'success', '::1'),
(0, 'PRS-GOV-001', '2025-05-07 13:19:10', 'view', 'users', 'page', 'success', '::1'),
(0, 'PRS-GOV-001', '2025-05-07 13:19:13', 'view', 'user_details', 'PRS-PUB-002', 'success', '::1'),
(0, 'PRS-GOV-001', '2025-05-07 13:19:15', 'view', 'user_details', 'PRS-PUB-002', 'success', '::1'),
(0, 'PRS-GOV-001', '2025-05-07 13:19:16', 'view', 'user_details', 'PRS-PUB-002', 'success', '::1'),
(0, 'PRS-GOV-001', '2025-05-07 13:19:16', 'view', 'user_details', 'PRS-PUB-002', 'success', '::1'),
(0, 'PRS-GOV-001', '2025-05-07 13:19:17', 'view', 'users', 'page', 'success', '::1'),
(0, 'PRS-GOV-001', '2025-05-07 13:19:18', 'view', 'user_details', 'PRS-PUB-002', 'success', '::1'),
(0, 'PRS-GOV-001', '2025-05-07 13:19:18', 'view', 'user_details', 'PRS-PUB-002', 'success', '::1'),
(0, 'PRS-GOV-001', '2025-05-07 13:19:19', 'view', 'user_details', 'PRS-PUB-002', 'success', '::1'),
(0, 'PRS-GOV-001', '2025-05-07 13:19:20', 'view', 'users', 'page', 'success', '::1'),
(0, 'PRS-GOV-001', '2025-05-07 13:19:22', 'view', 'user_details', 'PRS-MER-007', 'success', '::1'),
(0, 'PRS-GOV-001', '2025-05-07 13:19:22', 'view', 'user_details', 'PRS-MER-006', 'success', '::1'),
(0, 'PRS-GOV-001', '2025-05-07 13:19:24', 'view', 'dashboard', 'government', 'success', '::1'),
(0, 'PRS-GOV-001', '2025-05-07 13:19:27', 'view', 'users', 'page', 'success', '::1'),
(0, 'PRS-GOV-001', '2025-05-07 13:19:29', 'view', 'user_details', 'PRS-MER-001', 'success', '::1'),
(0, 'PRS-GOV-001', '2025-05-07 13:19:36', 'view', 'users', 'page', 'success', '::1'),
(0, 'PRS-GOV-001', '2025-05-07 13:19:37', 'view', 'user_details', 'PRS-MER-008', 'success', '::1'),
(0, 'PRS-GOV-001', '2025-05-07 13:19:37', 'view', 'user_details', 'PRS-MER-008', 'success', '::1'),
(0, 'PRS-GOV-001', '2025-05-07 13:19:55', 'logout', 'system', 'auth', 'success', '::1'),
(0, 'PRS-PUB-001', '2025-05-07 13:20:12', 'login', 'system', 'auth', 'success', '::1'),
(0, 'PRS-PUB-001', '2025-05-07 13:20:12', 'view', 'dashboard', 'public', 'success', '::1'),
(0, 'PRS-PUB-001', '2025-05-07 13:20:14', 'view', 'resource_finder', 'page', 'success', '::1'),
(0, 'PRS-PUB-001', '2025-05-07 13:31:12', 'view', 'resource_finder', 'page', 'success', '::1'),
(0, 'PRS-PUB-001', '2025-05-07 13:31:30', 'view', 'resource_finder', 'page', 'success', '::1'),
(0, 'PRS-PUB-001', '2025-05-07 13:33:04', 'view', 'dashboard', 'public', 'success', '::1'),
(0, 'PRS-PUB-001', '2025-05-07 13:33:07', 'view', 'vaccinations', 'page', 'success', '::1'),
(0, 'PRS-PUB-001', '2025-05-07 13:33:26', 'create', 'vaccination', 'record', 'success', '::1'),
(0, 'PRS-PUB-001', '2025-05-07 13:33:26', 'view', 'vaccinations', 'page', 'success', '::1'),
(0, 'PRS-PUB-001', '2025-05-07 13:33:30', 'view', 'dashboard', 'public', 'success', '::1'),
(0, 'PRS-PUB-001', '2025-05-07 13:34:01', 'view', 'profile', 'public', 'success', '::1'),
(0, 'PRS-PUB-001', '2025-05-07 13:34:05', 'view', 'vaccinations', 'page', 'success', '::1'),
(0, 'PRS-PUB-001', '2025-05-07 13:34:11', 'view', 'purchase_history', 'page', 'success', '::1'),
(0, 'PRS-PUB-001', '2025-05-07 13:36:17', 'logout', 'system', 'auth', 'success', '::1'),
(0, 'PRS-PUB-001', '2025-05-07 13:39:59', 'login', 'system', 'auth', 'success', '::1'),
(0, 'PRS-PUB-001', '2025-05-07 13:40:00', 'view', 'dashboard', 'public', 'success', '::1'),
(0, 'PRS-PUB-001', '2025-05-07 13:40:02', 'view', 'resource_finder', 'page', 'success', '::1'),
(0, 'PRS-PUB-001', '2025-05-07 13:40:04', 'view', 'resource_finder', 'page', 'success', '::1'),
(0, 'PRS-PUB-001', '2025-05-07 13:41:26', 'view', 'resource_finder', 'page', 'success', '::1'),
(0, 'PRS-PUB-001', '2025-05-07 13:41:29', 'view', 'purchase_history', 'page', 'success', '::1'),
(0, 'PRS-PUB-001', '2025-05-07 13:41:30', 'view', 'resource_finder', 'page', 'success', '::1'),
(0, 'PRS-PUB-001', '2025-05-07 13:41:46', 'view', 'merchant_inventory', '2', 'success', '::1'),
(0, 'PRS-PUB-001', '2025-05-07 13:43:09', 'view', 'resource_finder', 'page', 'success', '::1'),
(0, 'PRS-PUB-001', '2025-05-07 13:44:05', 'view', 'merchant_inventory', '10', 'success', '::1'),
(0, 'PRS-PUB-001', '2025-05-07 13:44:32', 'logout', 'system', 'auth', 'success', '::1'),
(0, 'PRS-GOV-001', '2025-05-07 13:44:38', 'login', 'system', 'auth', 'success', '::1'),
(0, 'PRS-GOV-001', '2025-05-07 13:44:38', 'view', 'dashboard', 'government', 'success', '::1'),
(0, 'PRS-GOV-001', '2025-05-07 13:45:56', 'view', 'users', 'page', 'success', '::1'),
(0, 'PRS-GOV-001', '2025-05-07 13:45:58', 'view', 'user_details', 'PRS-PUB-002', 'success', '::1'),
(0, 'PRS-GOV-001', '2025-05-07 13:46:16', 'view', 'inventory', 'page', 'success', '::1'),
(0, 'PRS-GOV-001', '2025-05-07 13:46:18', 'view', 'merchants', 'page', 'success', '::1'),
(0, 'PRS-GOV-001', '2025-05-07 13:46:21', 'view', 'inventory', 'page', 'success', '::1'),
(0, 'PRS-GOV-001', '2025-05-07 13:46:24', 'view', 'inventory', 'page', 'success', '::1'),
(0, 'PRS-GOV-001', '2025-05-07 13:46:26', 'view', 'merchants', 'page', 'success', '::1');

-- --------------------------------------------------------

--
-- Table structure for table `family_members`
--

CREATE TABLE `family_members` (
  `id` int(11) NOT NULL,
  `prs_id` varchar(20) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `dob` date NOT NULL,
  `relationship` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `family_members`
--

INSERT INTO `family_members` (`id`, `prs_id`, `first_name`, `last_name`, `dob`, `relationship`, `created_at`) VALUES
(1, 'PRS-PUB-001', 'Maria', 'Doe', '1992-05-18', 'Spouse', '2025-05-01 10:00:00'),
(2, 'PRS-PUB-001', 'Alex', 'Doe', '2020-03-10', 'Child', '2025-05-01 10:00:00'),
(3, 'PRS-PUB-002', 'Elena', 'Samouilidis', '2003-07-20', 'Sibling', '2025-05-01 10:00:00'),
(4, 'PRS-PUB-003', 'Sophia', 'Papadopoulos', '1975-08-12', 'Parent', '2025-05-01 10:00:00'),
(5, 'PRS-PUB-004', 'Andreas', 'Karagiannis', '1980-11-05', 'Spouse', '2025-05-01 10:00:00'),
(6, 'PRS-PUB-004', 'Eleni', 'Karagiannis', '2015-09-22', 'Child', '2025-05-01 10:00:00'),
(7, 'PRS-PUB-005', 'Stavros', 'Antoniou', '1982-04-14', 'Spouse', '2025-05-01 10:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `inventory_id` int(11) NOT NULL,
  `merchant_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `price` decimal(10,2) NOT NULL,
  `last_update` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`inventory_id`, `merchant_id`, `item_id`, `quantity`, `price`, `last_update`) VALUES
(1, 1, 1, 200, '5.99', '2025-05-05 17:40:36'),
(2, 1, 2, 150, '19.99', '2025-05-05 17:40:36'),
(3, 1, 3, 300, '9.99', '2025-05-05 17:40:36'),
(4, 1, 4, 100, '7.50', '2025-05-05 17:40:36'),
(5, 1, 5, 75, '6.25', '2025-05-05 17:40:36'),
(6, 2, 1, 150, '6.50', '2025-05-06 10:00:00'),
(7, 2, 2, 100, '21.99', '2025-05-06 10:00:00'),
(8, 2, 3, 250, '10.50', '2025-05-06 10:00:00'),
(9, 2, 6, 80, '4.99', '2025-05-06 10:00:00'),
(10, 2, 7, 120, '8.99', '2025-05-06 10:00:00'),
(11, 3, 1, 180, '5.75', '2025-05-06 10:00:00'),
(12, 3, 4, 120, '7.25', '2025-05-06 10:00:00'),
(13, 3, 8, 90, '15.99', '2025-05-06 10:00:00'),
(14, 3, 5, 60, '6.50', '2025-05-06 10:00:00'),
(15, 4, 9, 200, '3.99', '2025-05-06 10:00:00'),
(16, 4, 10, 150, '5.50', '2025-05-06 10:00:00'),
(17, 4, 11, 100, '6.75', '2025-05-06 10:00:00'),
(18, 4, 6, 180, '4.50', '2025-05-06 10:00:00'),
(19, 5, 2, 120, '20.50', '2025-05-06 10:00:00'),
(20, 5, 4, 90, '7.99', '2025-05-06 10:00:00'),
(21, 5, 12, 70, '12.50', '2025-05-06 10:00:00'),
(22, 5, 8, 60, '16.50', '2025-05-06 10:00:00'),
(23, 6, 13, 180, '3.75', '2025-05-06 10:00:00'),
(24, 6, 14, 120, '4.25', '2025-05-06 10:00:00'),
(25, 6, 9, 150, '3.50', '2025-05-06 10:00:00'),
(26, 7, 1, 100, '6.25', '2025-05-06 10:00:00'),
(27, 7, 2, 80, '22.50', '2025-05-06 10:00:00'),
(28, 7, 15, 60, '9.99', '2025-05-06 10:00:00'),
(29, 7, 5, 90, '6.99', '2025-05-06 10:00:00'),
(30, 8, 2, 130, '19.50', '2025-05-06 10:00:00'),
(31, 8, 8, 70, '15.75', '2025-05-06 10:00:00'),
(32, 8, 12, 50, '12.99', '2025-05-06 10:00:00'),
(33, 8, 4, 110, '7.75', '2025-05-06 10:00:00'),
(34, 9, 9, 220, '3.80', '2025-05-06 10:00:00'),
(35, 9, 10, 180, '5.25', '2025-05-06 10:00:00'),
(36, 9, 16, 90, '11.50', '2025-05-06 10:00:00'),
(37, 9, 6, 150, '4.75', '2025-05-06 10:00:00'),
(38, 10, 17, 100, '18.99', '2025-05-06 10:00:00'),
(39, 10, 18, 80, '22.50', '2025-05-06 10:00:00'),
(40, 10, 19, 60, '14.75', '2025-05-06 10:00:00'),
(41, 10, 20, 120, '16.99', '2025-05-06 10:00:00'),
(42, 11, 13, 200, '3.95', '2025-05-06 10:00:00'),
(43, 11, 9, 150, '3.75', '2025-05-06 10:00:00'),
(44, 11, 11, 100, '6.50', '2025-05-06 10:00:00'),
(45, 11, 6, 170, '4.80', '2025-05-06 10:00:00'),
(46, 12, 1, 140, '6.15', '2025-05-06 10:00:00'),
(47, 12, 2, 110, '20.25', '2025-05-06 10:00:00'),
(48, 12, 4, 95, '7.65', '2025-05-06 10:00:00'),
(49, 12, 5, 85, '6.45', '2025-05-06 10:00:00'),
(50, 12, 15, 65, '10.25', '2025-05-06 10:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `item_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `category` enum('essential','medical','restricted','normal') NOT NULL,
  `description` text DEFAULT NULL,
  `rationing_limit` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`item_id`, `name`, `category`, `description`, `rationing_limit`, `created_at`) VALUES
(1, 'Hand Sanitizer', 'essential', '500ml bottle of sanitizer', 2, '2025-05-05 17:40:36'),
(2, 'Face Mask (N95)', 'medical', 'Pack of 10 N95 masks', 1, '2025-05-05 17:40:36'),
(3, 'Toilet Paper', 'essential', '12 rolls pack', 1, '2025-05-05 17:40:36'),
(4, 'Pain Reliever', 'medical', '24 tablets pack', 2, '2025-05-05 17:40:36'),
(5, 'Disinfectant Spray', 'essential', '400ml can', 2, '2025-05-05 17:40:36'),
(6, 'Pasta', 'essential', '500g package', 3, '2025-05-05 17:40:36'),
(7, 'Rice', 'essential', '1kg package', 2, '2025-05-05 17:40:36'),
(8, 'COVID-19 Home Test Kit', 'medical', 'Pack of 2 antigen tests', 2, '2025-05-05 17:40:36'),
(9, 'Milk', 'essential', '1L carton', 4, '2025-05-05 17:40:36'),
(10, 'Bread', 'essential', 'Loaf of bread', 3, '2025-05-05 17:40:36'),
(11, 'Canned Soup', 'essential', '400g can', 5, '2025-05-05 17:40:36'),
(12, 'Digital Thermometer', 'medical', 'Quick-read digital thermometer', 1, '2025-05-05 17:40:36'),
(13, 'Flour', 'essential', '1kg package', 2, '2025-05-05 17:40:36'),
(14, 'Sugar', 'essential', '1kg package', 2, '2025-05-05 17:40:36'),
(15, 'Vitamin C Supplements', 'medical', '60 tablets bottle', 2, '2025-05-05 17:40:36'),
(16, 'Eggs', 'essential', 'Pack of 12 eggs', 2, '2025-05-05 17:40:36'),
(17, 'Oxygen Saturation Monitor', 'medical', 'Fingertip pulse oximeter', 1, '2025-05-05 17:40:36'),
(18, 'HEPA Air Purifier', 'medical', 'Small room air purifier', 1, '2025-05-05 17:40:36'),
(19, 'Medical Gloves', 'medical', 'Box of 100 disposable gloves', 1, '2025-05-05 17:40:36'),
(20, 'Face Shield', 'medical', 'Protective face shield', 2, '2025-05-05 17:40:36');

-- --------------------------------------------------------

--
-- Table structure for table `merchants`
--

CREATE TABLE `merchants` (
  `merchant_id` int(11) NOT NULL,
  `prs_id` varchar(20) NOT NULL,
  `business_name` varchar(100) NOT NULL,
  `business_type` varchar(50) NOT NULL,
  `license_number` varchar(50) NOT NULL,
  `status` enum('active','suspended','pending') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `merchants`
--

INSERT INTO `merchants` (`merchant_id`, `prs_id`, `business_name`, `business_type`, `license_number`, `status`) VALUES
(1, 'PRS-MER-001', 'Essential Supplies Store', 'Retail', 'BL-12345', 'active'),
(2, 'PRS-MER-002', 'Athina Pharmacy', 'Medical', 'GR-PH-23456', 'active'),
(3, 'PRS-MER-003', 'Thessaloniki Medical Supplies', 'Medical', 'GR-MS-34567', 'active'),
(4, 'PRS-MER-004', 'Mediterranean Groceries', 'Grocery', 'GR-GR-45678', 'active'),
(5, 'PRS-MER-005', 'Patras Health Center', 'Medical', 'GR-PH-56789', 'active'),
(6, 'PRS-MER-006', 'Heraklion Market', 'Grocery', 'GR-GR-67890', 'active'),
(7, 'PRS-MER-007', 'Larissa Pharmacy', 'Medical', 'GR-PH-78901', 'active'),
(8, 'PRS-MER-008', 'Volos Medical Supplies', 'Medical', 'GR-MS-89012', 'active'),
(9, 'PRS-MER-009', 'Ioannina Supermarket', 'Grocery', 'GR-GR-90123', 'active'),
(10, 'PRS-MER-010', 'Rhodes Medical Equipment', 'Medical', 'GR-ME-01234', 'active'),
(11, 'PRS-MER-011', 'Kalamata Groceries', 'Grocery', 'GR-GR-12345', 'active'),
(12, 'PRS-MER-012', 'Chania Pharmacy', 'Medical', 'GR-PH-23456', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `merchant_locations`
--

CREATE TABLE `merchant_locations` (
  `id` int(11) NOT NULL,
  `merchant_id` int(11) NOT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `merchant_locations`
--

INSERT INTO `merchant_locations` (`id`, `merchant_id`, `latitude`, `longitude`, `created_at`) VALUES
(1, 2, '40.6181000', '22.9064000', '2025-05-07 13:31:12'),
(2, 1, '40.6901000', '22.9774000', '2025-05-07 13:31:12'),
(3, 12, '40.5621000', '23.0414000', '2025-05-07 13:31:12'),
(4, 6, '40.7221000', '23.0124000', '2025-05-07 13:31:12'),
(5, 9, '40.5631000', '23.0084000', '2025-05-07 13:31:12'),
(6, 11, '40.6841000', '22.8894000', '2025-05-07 13:31:12'),
(7, 7, '40.6281000', '22.9564000', '2025-05-07 13:31:12'),
(8, 5, '40.7141000', '22.9594000', '2025-05-07 13:31:12'),
(9, 10, '40.6961000', '22.8994000', '2025-05-07 13:31:12'),
(10, 4, '40.5881000', '23.0304000', '2025-05-07 13:31:12'),
(11, 3, '40.6351000', '22.9314000', '2025-05-07 13:31:12'),
(12, 8, '40.5881000', '22.8494000', '2025-05-07 13:31:12');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `transaction_id` int(11) NOT NULL,
  `prs_id` varchar(20) NOT NULL,
  `merchant_id` int(11) NOT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `total_amount` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`transaction_id`, `prs_id`, `merchant_id`, `transaction_date`, `total_amount`) VALUES
(1, 'PRS-PUB-001', 1, '2025-05-01 09:30:00', '35.98'),
(2, 'PRS-PUB-002', 2, '2025-05-01 10:15:00', '28.49'),
(3, 'PRS-PUB-003', 3, '2025-05-01 11:45:00', '29.00'),
(4, 'PRS-PUB-004', 4, '2025-05-01 13:20:00', '18.24'),
(5, 'PRS-PUB-005', 5, '2025-05-01 14:45:00', '40.99'),
(6, 'PRS-PUB-001', 6, '2025-05-02 09:10:00', '16.25'),
(7, 'PRS-PUB-002', 7, '2025-05-02 10:30:00', '36.99'),
(8, 'PRS-PUB-003', 8, '2025-05-02 12:15:00', '28.25'),
(9, 'PRS-PUB-004', 9, '2025-05-02 14:00:00', '24.30'),
(10, 'PRS-PUB-005', 10, '2025-05-02 15:45:00', '57.49'),
(11, 'PRS-PUB-006', 11, '2025-05-03 08:30:00', '21.00'),
(12, 'PRS-PUB-007', 12, '2025-05-03 09:45:00', '34.50'),
(13, 'PRS-PUB-008', 1, '2025-05-03 11:00:00', '27.47'),
(14, 'PRS-PUB-009', 2, '2025-05-03 12:30:00', '43.97'),
(15, 'PRS-PUB-010', 3, '2025-05-03 14:15:00', '13.75'),
(16, 'PRS-PUB-001', 4, '2025-05-04 10:00:00', '20.74'),
(17, 'PRS-PUB-002', 5, '2025-05-04 11:30:00', '48.49'),
(18, 'PRS-PUB-003', 6, '2025-05-04 13:45:00', '15.25'),
(19, 'PRS-PUB-004', 7, '2025-05-04 15:15:00', '35.74'),
(20, 'PRS-PUB-005', 8, '2025-05-04 16:30:00', '51.25');

-- --------------------------------------------------------

--
-- Table structure for table `transaction_items`
--

CREATE TABLE `transaction_items` (
  `id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price_per_unit` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transaction_items`
--

INSERT INTO `transaction_items` (`id`, `transaction_id`, `item_id`, `quantity`, `price_per_unit`) VALUES
(1, 1, 1, 2, '5.99'),
(2, 1, 2, 1, '19.99'),
(3, 1, 3, 1, '9.99'),
(4, 2, 1, 1, '6.50'),
(5, 2, 2, 1, '21.99'),
(6, 3, 1, 2, '5.75'),
(7, 3, 4, 1, '7.25'),
(8, 3, 8, 1, '15.99'),
(9, 4, 9, 2, '3.99'),
(10, 4, 10, 1, '5.50'),
(11, 4, 11, 1, '6.75'),
(12, 5, 2, 1, '20.50'),
(13, 5, 4, 1, '7.99'),
(14, 5, 12, 1, '12.50'),
(15, 6, 13, 2, '3.75'),
(16, 6, 14, 1, '4.25'),
(17, 6, 9, 1, '3.50'),
(18, 7, 1, 1, '6.25'),
(19, 7, 2, 1, '22.50'),
(20, 7, 15, 1, '9.99'),
(21, 8, 2, 1, '19.50'),
(22, 8, 8, 1, '15.75'),
(23, 9, 9, 2, '3.80'),
(24, 9, 10, 2, '5.25'),
(25, 9, 6, 1, '4.75'),
(26, 10, 17, 1, '18.99'),
(27, 10, 18, 1, '22.50'),
(28, 10, 19, 1, '14.75'),
(29, 11, 13, 2, '3.95'),
(30, 11, 9, 2, '3.75'),
(31, 11, 11, 1, '6.50'),
(32, 12, 1, 2, '6.15'),
(33, 12, 2, 1, '20.25'),
(34, 12, 15, 1, '10.25'),
(35, 13, 3, 1, '9.99'),
(36, 13, 4, 1, '7.50'),
(37, 13, 5, 1, '6.25'),
(38, 13, 1, 1, '5.99'),
(39, 14, 2, 1, '21.99'),
(40, 14, 6, 1, '4.99'),
(41, 14, 7, 2, '8.99'),
(42, 15, 4, 1, '7.25'),
(43, 15, 5, 1, '6.50'),
(44, 16, 9, 2, '3.99'),
(45, 16, 10, 1, '5.50'),
(46, 16, 11, 1, '6.75'),
(47, 17, 2, 1, '20.50'),
(48, 17, 4, 1, '7.99'),
(49, 17, 12, 1, '12.50'),
(50, 17, 8, 1, '16.50'),
(51, 18, 13, 2, '3.75'),
(52, 18, 14, 1, '4.25'),
(53, 18, 9, 1, '3.50'),
(54, 19, 1, 1, '6.25'),
(55, 19, 2, 1, '22.50'),
(56, 19, 5, 1, '6.99'),
(57, 20, 2, 1, '19.50'),
(58, 20, 8, 1, '15.75'),
(59, 20, 12, 1, '12.99'),
(60, 20, 4, 1, '7.75');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `prs_id` varchar(20) NOT NULL,
  `national_id` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `dob` date NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `role` enum('public','government','merchant') NOT NULL DEFAULT 'public',
  `password` varchar(255) NOT NULL,
  `status` enum('active','suspended','pending') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`prs_id`, `national_id`, `first_name`, `last_name`, `dob`, `email`, `phone`, `address`, `city`, `postal_code`, `role`, `password`, `status`, `created_at`, `updated_at`) VALUES
('PRS-GOV-001', 'ENC-G12345', 'Admin', 'User', '1980-01-01', 'admin@gov.example', '1234567890', '123 Gov Street', 'Athens', '10001', 'government', '$2y$10$abcdefghijklmnopqrstuv', 'active', '2025-05-05 17:40:36', '2025-05-05 17:40:36'),
('PRS-GOV-002', 'ENC-G23456', 'Maria', 'Papadopoulou', '1982-04-15', 'maria@gov.example', '2101234567', '45 Ermou Street', 'Athens', '10563', 'government', '$2y$10$abcdefghijklmnopqrstuv', 'active', '2025-05-05 17:40:36', '2025-05-05 17:40:36'),
('PRS-GOV-003', 'ENC-G34567', 'Nikos', 'Georgiou', '1975-08-22', 'nikos@gov.example', '2310123456', '78 Tsimiski Street', 'Thessaloniki', '54622', 'government', '$2y$10$abcdefghijklmnopqrstuv', 'active', '2025-05-05 17:40:36', '2025-05-05 17:40:36'),
('PRS-MER-001', 'ENC-M12345', 'Store', 'Owner', '1975-05-15', 'store@example.com', '9876543210', '456 Market St', 'Athens', '20002', 'merchant', '$2y$10$abcdefghijklmnopqrstuv', 'active', '2025-05-05 17:40:36', '2025-05-05 17:40:36'),
('PRS-MER-002', 'ENC-M23456', 'Giorgos', 'Dimitriou', '1978-06-20', 'giorgos@athina-pharmacy.gr', '2101234567', '25 Panepistimiou Street', 'Athens', '10671', 'merchant', '$2y$10$abcdefghijklmnopqrstuv', 'active', '2025-05-05 17:40:36', '2025-05-05 17:40:36'),
('PRS-MER-003', 'ENC-M34567', 'Eleni', 'Papanikolaou', '1982-09-10', 'eleni@thess-medical.gr', '2310987654', '122 Egnatia Street', 'Thessaloniki', '54622', 'merchant', '$2y$10$abcdefghijklmnopqrstuv', 'active', '2025-05-05 17:40:36', '2025-05-05 17:40:36'),
('PRS-MER-004', 'ENC-M45678', 'Kostas', 'Andreou', '1980-03-05', 'kostas@med-groceries.gr', '2310456789', '45 Aristotelous Square', 'Thessaloniki', '54624', 'merchant', '$2y$10$abcdefghijklmnopqrstuv', 'active', '2025-05-05 17:40:36', '2025-05-05 17:40:36'),
('PRS-MER-005', 'ENC-M56789', 'Dimitra', 'Vasileiou', '1985-07-18', 'dimitra@patras-health.gr', '2610123456', '32 Maizonos Street', 'Patras', '26221', 'merchant', '$2y$10$abcdefghijklmnopqrstuv', 'active', '2025-05-05 17:40:36', '2025-05-05 17:40:36'),
('PRS-MER-006', 'ENC-M67890', 'Andreas', 'Nikolaidis', '1979-12-05', 'andreas@heraklion-market.gr', '2810234567', '15 25th August Street', 'Heraklion', '71202', 'merchant', '$2y$10$abcdefghijklmnopqrstuv', 'active', '2025-05-05 17:40:36', '2025-05-05 17:40:36'),
('PRS-MER-007', 'ENC-M78901', 'Sofia', 'Athanasiou', '1983-02-15', 'sofia@larissa-pharmacy.gr', '2410345678', '28 Papanastasiou Street', 'Larissa', '41222', 'merchant', '$2y$10$abcdefghijklmnopqrstuv', 'active', '2025-05-05 17:40:36', '2025-05-05 17:40:36'),
('PRS-MER-008', 'ENC-M89012', 'Yannis', 'Papadakis', '1976-11-30', 'yannis@volos-medical.gr', '2421456789', '9 Iasonos Street', 'Volos', '38221', 'merchant', '$2y$10$abcdefghijklmnopqrstuv', 'active', '2025-05-05 17:40:36', '2025-05-05 17:40:36'),
('PRS-MER-009', 'ENC-M90123', 'Irini', 'Christou', '1984-05-25', 'irini@ioannina-super.gr', '2651567890', '17 Dodonis Street', 'Ioannina', '45221', 'merchant', '$2y$10$abcdefghijklmnopqrstuv', 'active', '2025-05-05 17:40:36', '2025-05-05 17:40:36'),
('PRS-MER-010', 'ENC-M01234', 'Alexandros', 'Antoniou', '1977-08-12', 'alex@rhodes-medical.gr', '2241678901', '22 Amerikis Street', 'Rhodes', '85100', 'merchant', '$2y$10$abcdefghijklmnopqrstuv', 'active', '2025-05-05 17:40:36', '2025-05-05 17:40:36'),
('PRS-MER-011', 'ENC-M12345', 'Katerina', 'Michailidou', '1981-06-08', 'katerina@kalamata-groceries.gr', '2721789012', '8 Aristomenous Street', 'Kalamata', '24100', 'merchant', '$2y$10$abcdefghijklmnopqrstuv', 'active', '2025-05-05 17:40:36', '2025-05-05 17:40:36'),
('PRS-MER-012', 'ENC-M23456', 'Stefanos', 'Panagiotou', '1979-04-17', 'stefanos@chania-pharmacy.gr', '2821890123', '12 Tzanakaki Street', 'Chania', '73134', 'merchant', '$2y$10$abcdefghijklmnopqrstuv', 'active', '2025-05-05 17:40:36', '2025-05-05 17:40:36'),
('PRS-PUB-001', 'ENC-P12345', 'John', 'Doe', '1990-10-25', 'john@example.com', '5551234567', '789 Main St', 'Athens', '10436', 'public', '$2y$10$abcdefghijklmnopqrstuv', 'active', '2025-05-05 17:40:36', '2025-05-05 17:40:36'),
('PRS-PUB-002', 'ENC-68f3130922', 'Nikolaos', 'Samouilidis', '2000-01-03', 'nik.samouilidis@gmail.com', '6979181657', 'Iatrou Archaggelou 59', 'Thessaloniki', '56728', 'public', '$2y$10$vxZpjPwOPVCw/VEtjymv/e.umOZJJ9Iqh5TK0Fi84dyEwC6Sc5aOi', 'active', '2025-05-05 17:47:07', '2025-05-05 17:47:07'),
('PRS-PUB-003', 'ENC-P34567', 'Georgia', 'Papadopoulos', '1998-03-12', 'georgia.p@example.com', '6981234567', '45 Ermou Street', 'Athens', '10563', 'public', '$2y$10$abcdefghijklmnopqrstuv', 'active', '2025-05-05 17:40:36', '2025-05-05 17:40:36'),
('PRS-PUB-004', 'ENC-P45678', 'Dimitris', 'Karagiannis', '1985-11-08', 'dimitris.k@example.com', '6951234567', '22 Egnatia Street', 'Thessaloniki', '54622', 'public', '$2y$10$abcdefghijklmnopqrstuv', 'active', '2025-05-05 17:40:36', '2025-05-05 17:40:36'),
('PRS-PUB-005', 'ENC-P56789', 'Konstantina', 'Antoniou', '1992-07-15', 'konstantina.a@example.com', '6971234567', '8 Korinthou Street', 'Patras', '26221', 'public', '$2y$10$abcdefghijklmnopqrstuv', 'active', '2025-05-05 17:40:36', '2025-05-05 17:40:36'),
('PRS-PUB-006', 'ENC-P67890', 'Anastasios', 'Alexiou', '1982-05-20', 'anastasios.a@example.com', '6931234567', '13 Eleftherias Street', 'Heraklion', '71201', 'public', '$2y$10$abcdefghijklmnopqrstuv', 'active', '2025-05-05 17:40:36', '2025-05-05 17:40:36'),
('PRS-PUB-007', 'ENC-P78901', 'Eleftheria', 'Papazoglou', '1995-09-03', 'eleftheria.p@example.com', '6941234567', '27 Papanastasiou Street', 'Larissa', '41222', 'public', '$2y$10$abcdefghijklmnopqrstuv', 'active', '2025-05-05 17:40:36', '2025-05-05 17:40:36'),
('PRS-PUB-008', 'ENC-P89012', 'Panagiotis', 'Demetriou', '1988-12-18', 'panagiotis.d@example.com', '6961234567', '11 Dimitriados Street', 'Volos', '38221', 'public', '$2y$10$abcdefghijklmnopqrstuv', 'active', '2025-05-05 17:40:36', '2025-05-05 17:40:36'),
('PRS-PUB-009', 'ENC-P90123', 'Christina', 'Nikolaidou', '1997-02-25', 'christina.n@example.com', '6921234567', '19 Dodonis Street', 'Ioannina', '45221', 'public', '$2y$10$abcdefghijklmnopqrstuv', 'active', '2025-05-05 17:40:36', '2025-05-05 17:40:36'),
('PRS-PUB-010', 'ENC-P01234', 'Stefanos', 'Georgiou', '1993-06-10', 'stefanos.g@example.com', '6911234567', '31 Amerikis Street', 'Rhodes', '85100', 'public', '$2y$10$abcdefghijklmnopqrstuv', 'active', '2025-05-05 17:40:36', '2025-05-05 17:40:36');

-- --------------------------------------------------------

--
-- Table structure for table `vaccinations`
--

CREATE TABLE `vaccinations` (
  `vaccination_id` int(11) NOT NULL,
  `prs_id` varchar(20) NOT NULL,
  `vaccine_name` varchar(100) NOT NULL,
  `vaccination_date` date NOT NULL,
  `facility_name` varchar(100) NOT NULL,
  `batch_number` varchar(50) NOT NULL,
  `certificate_file` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vaccinations`
--

INSERT INTO `vaccinations` (`vaccination_id`, `prs_id`, `vaccine_name`, `vaccination_date`, `facility_name`, `batch_number`, `certificate_file`) VALUES
(1, 'PRS-PUB-001', 'Astra', '2016-01-03', 'Athens', '19284754', '');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `merchant_locations`
--
ALTER TABLE `merchant_locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `merchant_id` (`merchant_id`);

--
-- Indexes for table `vaccinations`
--
ALTER TABLE `vaccinations`
  ADD PRIMARY KEY (`vaccination_id`),
  ADD KEY `prs_id` (`prs_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `merchant_locations`
--
ALTER TABLE `merchant_locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `vaccinations`
--
ALTER TABLE `vaccinations`
  MODIFY `vaccination_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;


