# app.py

def seed_example_users():
    users = [
        ("Alex", "Tester", "alex@example.com", "12345", "555-0100", "123 Hackathon Ave", 0, 1),
        ("Sam", "Smith", "sam@bank.com", "12345", "555-0101", "456 Main St", 0, 1),
        ("Jane", "Doe", "jane@bank.com", "12345", "555-0102", "789 Elm St", 1, 1),
        ("Bob", "Brown", "bob@bank.com", "12345", "555-0103", "321 Oak St", 0, 1),
    ]
    with get_db() as conn:
        for u in users:
            conn.execute("""
                INSERT OR IGNORE INTO USERS(first_name, last_name, email, password, phone, address, is_admin, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            """, u)
        # Optionally, create accounts for each user
        for email in ["alex@example.com", "sam@bank.com", "jane@bank.com", "bob@bank.com"]:
            user_id = conn.execute("SELECT id FROM USERS WHERE email=?", (email,)).fetchone()["id"]
            conn.execute("""INSERT OR IGNORE INTO ACCOUNT_TYPES(name, description)
                            VALUES('checking','Default checking')""")
            acct_type_id = conn.execute("SELECT id FROM ACCOUNT_TYPES WHERE name='checking'").fetchone()["id"]
            acct_num = f"RB-{user_id:06d}"
            conn.execute("""INSERT OR IGNORE INTO ACCOUNTS(user_id, account_type_id, account_number, balance)
                            VALUES (?, ?, ?, ?)""", (user_id, acct_type_id, acct_num, 1000.00))

def init_db():
    if not pathlib.Path(DB_PATH).exists():
        with get_db() as conn, open(SCHEMA_PATH, "r", encoding="utf-8") as f:
            conn.executescript(f.read())
        seed_example_users()
import sqlite3, pathlib
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel

DB_PATH = "DATABASE.sql"
SCHEMA_PATH = "DATABASE.sql"

app = FastAPI()

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],   
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

def get_db():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    return conn

def init_db():
    if not pathlib.Path(DB_PATH).exists():
        with get_db() as conn, open(SCHEMA_PATH, "r", encoding="utf-8") as f:
            conn.executescript(f.read())
        # Optional: seed one user + account for testing
        with get_db() as conn:
            conn.execute("""
                INSERT INTO USERS(first_name,last_name,email,password,phone,address,is_admin)
                VALUES(?,?,?,?,?,?,?)
            """, ("Alex","Tester","alex@example.com","pass1234","555-0100","123 Hackathon Ave",0))
            user_id = conn.execute("SELECT id FROM USERS WHERE email=?",
                                   ("alex@example.com",)).fetchone()["id"]
            conn.execute("""INSERT OR IGNORE INTO ACCOUNT_TYPES(name, description)
                            VALUES('checking','Default checking')""")
            acct_type_id = conn.execute("SELECT id FROM ACCOUNT_TYPES WHERE name='checking'").fetchone()["id"]
            conn.execute("""INSERT INTO ACCOUNTS(user_id,account_type_id,account_number,balance)
                            VALUES(?,?,?,?)""", (user_id, acct_type_id, "RB-000001", 1000.00))

class LoginPayload(BaseModel):
    username: str   
    password: str

@app.post("/api/login")
def login(payload: LoginPayload):
    username = payload.username.strip()
    password = payload.password

    with get_db() as conn:
        user = conn.execute(
            "SELECT * FROM USERS WHERE email = ? AND is_active = 1",
            (username,)
        ).fetchone()

        if user is None:
            user = conn.execute("""
                SELECT u.* FROM USERS u
                JOIN ACCOUNTS a ON a.user_id = u.id
                WHERE a.account_number = ? AND u.is_active = 1
            """, (username,)).fetchone()

        if user is None:
            return {"ok": False, "error": "User not found"}

        # NOTE: Passwords are stored in plaintext per your schema.
        # For a real app, hash with bcrypt/argon2 and compare hashes.
        if user["password"] != password:
            return {"ok": False, "error": "Invalid credentials"}

        return {
            "ok": True,
            "user": {
                "id": user["id"],
                "first_name": user["first_name"],
                "last_name": user["last_name"],
                "email": user["email"],
                "is_admin": bool(user["is_admin"])
            }
        }

if __name__ == "__main__":
    init_db()
