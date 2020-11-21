CREATE TABLE `accounts` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT '',
  `routing_number` varchar(20) NOT NULL DEFAULT '',
  `account_number` varchar(20) NOT NULL DEFAULT '',
  `balance` float(9,2) NOT NULL DEFAULT '0.00',
  `currency` char(3) NOT NULL DEFAULT 'CAD',
  `balance_date` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `routing_number` (`routing_number`,`account_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE `categories` (
  `name` varchar(100) NOT NULL DEFAULT '',
  `hidden` enum('yes','no') NOT NULL DEFAULT 'no',
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `categories` (`name`, `hidden`)
VALUES
  ('Alcohol & Bars', 'no'),
  ('Audio-Video', 'no'),
  ('Auto: Accessories', 'no'),
  ('Auto: Car Wash', 'no'),
  ('Auto: Charging', 'no'),
  ('Auto: Contraventions', 'no'),
  ('Auto: Drivers License', 'no'),
  ('Auto: Fuel', 'no'),
  ('Auto: Immatriculation', 'no'),
  ('Auto: Insurance', 'no'),
  ('Auto: New Car', 'no'),
  ('Auto: Parking', 'no'),
  ('Auto: Public Transportation', 'no'),
  ('Auto: Rentals & Taxi', 'no'),
  ('Auto: Service & Parts', 'no'),
  ('Auto: Tolls', 'no'),
  ('Books', 'no'),
  ('Clothing', 'no'),
  ('Clothing: Laundry', 'no'),
  ('Computers', 'no'),
  ('Computers: Home Server', 'no'),
  ('Computers: Networking', 'no'),
  ('Computers: Software', 'no'),
  ('Computers: Workstations', 'no'),
  ('Entertainment: Movies', 'no'),
  ('Entertainment: Video Games', 'no'),
  ('Family Outing', 'no'),
  ('Fees & Charges: Bank Fee', 'no'),
  ('Fees & Charges: Late Fee', 'no'),
  ('Fees & Charges: Loan Interest', 'no'),
  ('Food & Dining: Coffee Shops', 'no'),
  ('Food & Dining: Fast Food', 'no'),
  ('Food & Dining: Groceries', 'no'),
  ('Food & Dining: Restaurants', 'no'),
  ('Giving: Charity', 'no'),
  ('Giving: Gift', 'no'),
  ('Health: Dentist', 'no'),
  ('Health: Optometrist & Optician', 'no'),
  ('Health: Podiatrist', 'no'),
  ('Home: Automation', 'no'),
  ('Home: Condo Fees', 'no'),
  ('Home: Electricity', 'no'),
  ('Home: Furnishings', 'no'),
  ('Home: Improvements', 'no'),
  ('Home: Insurance', 'no'),
  ('Home: Internet', 'no'),
  ('Home: Lawn & Garden', 'no'),
  ('Home: Mortgage & Rent', 'no'),
  ('Home: Natural Gas', 'no'),
  ('Home: Office Supplies', 'no'),
  ('Home: Phone', 'no'),
  ('Home: Repairs', 'no'),
  ('Home: Services', 'no'),
  ('Home: Shopping', 'no'),
  ('Home: Supplies', 'no'),
  ('Income', 'no'),
  ('Income: Credit Card Cashback', 'no'),
  ('Income: Dividend & Cap Gains', 'no'),
  ('Income: Govt Child Benefits', 'no'),
  ('Income: Interest', 'no'),
  ('Income: Paycheck', 'no'),
  ('Kids: Activities', 'no'),
  ('Kids: Babysitter & Daycare', 'no'),
  ('Kids: Birthdays', 'no'),
  ('Kids: School: Lunch', 'no'),
  ('Kids: School: Pictures', 'no'),
  ('Kids: School: Supplies', 'no'),
  ('Kids: School: Tuition', 'no'),
  ('Kids: School: Tuition Savings Plan', 'no'),
  ('Kids: Summer Camps', 'no'),
  ('Kids: Toys', 'no'),
  ('Legal', 'no'),
  ('Life Insurance', 'no'),
  ('Mobile Phone: Apps', 'no'),
  ('Mobile Phone: Device', 'no'),
  ('Mobile Phone: Network', 'no'),
  ('Music', 'no'),
  ('Personal Care', 'no'),
  ('Personal Care: Hair', 'no'),
  ('Personal Care: Pharmacy', 'no'),
  ('Pets', 'no'),
  ('Professional Expenses', 'no'),
  ('Professional Licenses', 'no'),
  ('Professional Services', 'no'),
  ('Shipping', 'no'),
  ('Taxes', 'no'),
  ('Taxes: Federal', 'no'),
  ('Taxes: Municipal', 'no'),
  ('Taxes: Provincial', 'no'),
  ('Transfer', 'yes'),
  ('Transfer: Credit Card Payment', 'yes'),
  ('Transfer: For Cash Spending', 'yes'),
  ('Transfer: Investments', 'yes'),
  ('Travel: Hotel', 'no'),
  ('Travel: Vacation', 'no'),
  ('Watches', 'no');


CREATE TABLE `post_processing` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `regex` varchar(100) NOT NULL DEFAULT '',
  `amount_equals` float(7,2) DEFAULT NULL,
  `display_name` varchar(255) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `prio` tinyint(3) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `regex` (`regex`,`amount_equals`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `post_processing` (`id`, `regex`, `display_name`, `category`, `tags`, `prio`)
VALUES
  (1, 'EFT Deposit from CANADA', 'Canada child tax benefit', 'Income: Govt Child Benefits', NULL, 0),
  (2, 'LA BOITE ELECTRONIQUE', 'EBOX', 'Home: Internet', NULL, 0),
  (13, 'EFT Deposit from MANUVIE', 'Manuvie', 'Health Insurance', NULL, 0);


CREATE TABLE `tags` (
  `name` varchar(30) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `tags` (`name`)
VALUES
  ('Reimbursable'),
  ('Reimbursed'),
  ('Tax Related'),
  ('Vacation');


CREATE TABLE `transactions` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int(11) unsigned NOT NULL,
  `unique_id` varchar(100) DEFAULT '',
  `date` datetime NOT NULL,
  `type` varchar(20) DEFAULT NULL,
  `amount` float(9,2) NOT NULL,
  `display_name` varchar(255) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `memo` text DEFAULT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `post_processed` enum('yes','no') NOT NULL DEFAULT 'no',
  `linked_txn_id` int(11) unsigned DEFAULT NULL,
  `hidden` enum('yes','no') NOT NULL DEFAULT 'no',
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_id` (`account_id`,`unique_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE VIEW `v_transactions_reports` AS
  SELECT
      t.id AS `id`,
      t.date AS `date`,
      SUBSTR(t.date, 1, 7) AS `month`,
      t.amount AS `amount`,
      t.category AS `category`,
      SUBSTR(t.category, 1, LENGTH(t.category) - LOCATE(':', REVERSE(t.category))) AS `group_by_category`,
      t.tags AS `tags`,
      IFNULL(t.display_name, t.name) AS `name`,
      t.memo AS `memo`,
      t.type AS `type`,
      t.account_id AS `account_id`,
      IF(t.hidden = 'yes' OR t.category IN (SELECT `name` from categories where hidden = 'yes'), 'yes', 'no') AS hidden
  FROM transactions t;
