from fastapi import FastAPI

from app.routes import users_router

app = FastAPI(title="CNM FastAPI Class A")

app.include_router(users_router)
