"""
DB connection for Python cron. Same DB as PHP (SQLite or MariaDB from .env).
"""
import os
import sqlite3
from pathlib import Path

def get_connection(base_dir: str):
    """Return a DB connection (sqlite3 or pymysql)."""
    from env import get, get_required, load_dotenv
    load_dotenv(base_dir)
    driver = get("DB_DRIVER", "sqlite").lower()
    dsn = get_required("DB_DSN")
    if driver == "sqlite":
        path = dsn.replace("sqlite:", "")
        if not path.startswith("/") and ":" not in path[:2]:
            path = str(Path(base_dir) / path.replace("/", os.sep))
        return sqlite3.connect(path)
    if driver in ("mariadb", "mysql"):
        try:
            import pymysql
        except ImportError:
            raise RuntimeError("pip install pymysql for MariaDB")
        # Parse DSN: mysql:host=...;dbname=...;charset=...
        parts = {}
        for part in dsn.split(";"):
            if "=" in part:
                k, v = part.strip().split("=", 1)
                parts[k.strip().lower()] = v.strip()
        return pymysql.connect(
            host=parts.get("host", "127.0.0.1"),
            user=os.environ.get("DB_USER", ""),
            password=os.environ.get("DB_PASSWORD", ""),
            database=parts.get("dbname", ""),
            charset=parts.get("charset", "utf8mb4"),
        )
    raise RuntimeError(f"Unsupported DB_DRIVER: {driver}")
