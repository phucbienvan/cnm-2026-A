from fastapi import FastAPI
from app.routes import user

app = FastAPI(title="CNM FastAPI Class A")

app.include_router(user.router)

