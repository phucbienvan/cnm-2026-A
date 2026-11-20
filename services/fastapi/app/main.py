from fastapi import FastAPI
from app.routes import router

app = FastAPI(title="CNM FastAPI Class A")

app.include_router(router)
