from fastapi import APIRouter

# Router kiểm tra trạng thái hoạt động của server (Health check)
router = APIRouter(tags=["health"])

@router.get("/health")
def health_check():
    return {"status": "ok"}

