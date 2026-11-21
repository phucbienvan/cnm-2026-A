from fastapi import FastAPI

from app.routes import health, users

app = FastAPI(title="CNM FastAPI Class A")
app.include_router(users.router, tags=["users"])
app.include_router(health.router, tags=["health"])
