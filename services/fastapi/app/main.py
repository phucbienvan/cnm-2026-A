from fastapi import FastAPI

from app.routes.user import router as user_router

app = FastAPI(title="CNM FastAPI Class A")
app.include_router(user_router)

@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}