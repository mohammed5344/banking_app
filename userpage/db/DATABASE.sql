-- Users table
CREATE TABLE IF NOT EXISTS USERS (
    id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(191) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    date_of_birth DATE,
    address VARCHAR(255),
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Account types table
CREATE TABLE IF NOT EXISTS ACCOUNT_TYPES (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    description TEXT,
    minimum_balance DECIMAL(10, 2) DEFAULT 0,
    monthly_fee DECIMAL(8, 2) DEFAULT 0,
    interest_rate DECIMAL(5, 4) DEFAULT 0,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
-- Accounts table
CREATE TABLE IF NOT EXISTS ACCOUNTS (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    account_type_id INTEGER NOT NULL,
    account_number TEXT UNIQUE NOT NULL,
    balance DECIMAL(15, 2) NOT NULL DEFAULT 0,
    status TEXT CHECK(status IN ('active', 'frozen', 'closed')) DEFAULT 'active',
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES USERS(id),
    FOREIGN KEY (account_type_id) REFERENCES ACCOUNT_TYPES(id)
);
-- Transactions table
CREATE TABLE IF NOT EXISTS TRANSACTIONS (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    account_id INTEGER NOT NULL,
    transaction_type TEXT CHECK(
        transaction_type IN ('deposit', 'withdrawal', 'transfer', 'fee')
    ) NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    balance_after DECIMAL(15, 2) NOT NULL,
    description TEXT NOT NULL,
    recipient_account_id INTEGER,
    reference_number TEXT UNIQUE,
    transaction_method TEXT CHECK(
        transaction_method IN ('atm', 'online', 'mobile', 'branch')
    ) NOT NULL DEFAULT 'online',
    status TEXT CHECK(status IN ('pending', 'completed', 'failed')) DEFAULT 'completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES ACCOUNTS(id),
    FOREIGN KEY (recipient_account_id) REFERENCES ACCOUNTS(id)
);
-- Cards table
CREATE TABLE IF NOT EXISTS CARDS (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    account_id INTEGER NOT NULL,
    card_number TEXT UNIQUE NOT NULL,
    is_credit BOOLEAN NOT NULL DEFAULT 0,
    expiry_date DATE NOT NULL,
    cvv TEXT NOT NULL,
    credit_limit DECIMAL(10, 2),
    status TEXT CHECK(status IN ('active', 'blocked', 'expired')) DEFAULT 'active',
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES USERS(id),
    FOREIGN KEY (account_id) REFERENCES ACCOUNTS(id)
);
-- Loans table
CREATE TABLE IF NOT EXISTS LOANS (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    loan_type TEXT CHECK(loan_type IN ('personal', 'auto', 'mortgage')) NOT NULL,
    principal_amount DECIMAL(15, 2) NOT NULL,
    current_balance DECIMAL(15, 2) NOT NULL,
    interest_rate DECIMAL(5, 4) NOT NULL,
    monthly_payment DECIMAL(10, 2) NOT NULL,
    status TEXT CHECK(
        status IN ('pending', 'approved', 'active', 'paid_off')
    ) DEFAULT 'pending',
    next_payment_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES USERS(id)
);
-- Beneficiaries table
CREATE TABLE IF NOT EXISTS BENEFICIARIES (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    account_number TEXT NOT NULL,
    bank_name TEXT NOT NULL,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES USERS(id)
);
CREATE TABLE IF NOT EXISTS BUDGETS (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    category VARCHAR(50) NOT NULL,
    item VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES USERS(id)
);

CREATE INDEX idx_user_budget ON BUDGETS(user_id);
