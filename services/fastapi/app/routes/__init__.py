from fastapi import APIRouter
from app.routes.health import router as health_router
from app.routes.user import router as user_router

# Gom các router con vào router chính
api_router = APIRouter()
api_router.include_router(health_router)
api_router.include_router(user_router)

