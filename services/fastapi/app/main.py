from fastapi import FastAPI

from app.routes import users

app = FastAPI(title="CNM FastAPI Class A")

app.include_router(users.router)
