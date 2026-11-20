from fastapi import APIRouter

from app.schemas.user import UserResponse

router = APIRouter(tags=["users"])


@router.post("/create-user", response_model=UserResponse)
def create_user() -> dict[str, str]:
    return {
        "status": "ok",
        "message": "User created successfully"
    }


@router.get("/get-user/{user_id}", response_model=UserResponse)
def get_user(user_id: int) -> dict[str, str]:
    return {
        "status": "ok",
        "message": f"User {user_id} retrieved successfully"
    }


@router.delete("/delete-user/{user_id}", response_model=UserResponse)
def delete_user(user_id: int) -> dict[str, str]:
    return {
        "status": "ok",
        "message": f"User {user_id} deleted successfully"
    }
