from fastapi import APIRouter
from app.routes.health import router as health_router
from app.routes.users import router as user_router

router = APIRouter()
router.include_router(health_router)
router.include_router(user_router)

__all__ = ["router", "health_router", "user_router"]
