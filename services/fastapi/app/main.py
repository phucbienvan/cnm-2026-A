from fastapi import FastAPI
from app.routes import register_routes

app = FastAPI(title="CNM FastAPI Class A")

register_routes(app)
