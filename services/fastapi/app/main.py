from fastapi import FastAPI

from app.routes import user_router

app = FastAPI(title="CNM FastAPI Class A")

app.include_router(user_router)
