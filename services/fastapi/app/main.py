from fastapi import FastAPI
from app.routes.user import router as user_router
from app.db.base import engine
from app.db.base import get_db
from sqlalchemy import text

app = FastAPI(title="CNM FastAPI Class A")
app.include_router(user_router)

@app.get("/health")
def health() -> dict[str, str]:
    with engine.connect() as connection:
        connection.execute(text("SELECT 1"))

    return {
        "msg": "database connected"
    }