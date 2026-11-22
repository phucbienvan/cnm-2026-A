from fastapi import FastAPI
from .routes.user import router as user_router

app = FastAPI(title="CNM FastAPI Class A")
app.include_router(user_router)
