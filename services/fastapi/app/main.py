from fastapi import FastAPI
from app.routes import user

app = FastAPI(title="CNM FastAPI")

app.include_router(user.router)

@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}
