from fastapi import FastAPI
from app.routes.users import router as users_router

app = FastAPI(title="CNM FastAPI Class A")

app.include_router(users_router)