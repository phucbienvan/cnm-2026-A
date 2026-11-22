from fastapi import FastAPI
from app.routes import router as api_router

app = FastAPI(title="CNM FastAPI Class A")

app.include_router(api_router)
