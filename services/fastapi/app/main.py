from fastapi import FastAPI
from app.routes import health_router, users_router

app = FastAPI(title="CNM FastAPI Class A")

app.include_router(health_router)
app.include_router(users_router)
