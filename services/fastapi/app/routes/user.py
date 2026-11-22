from fastapi import APIRouter

router = APIRouter(prefix="/users", tags=["users"])


@router.post("")
def create_user() -> dict[str, str]:
    return {
        "status": "ok",
        "message": "User created successfully",
    }


@router.get("/{user_id}")
def get_user(user_id: int) -> dict[str, str]:
    return {
        "status": "ok",
        "message": f"User {user_id} retrieved successfully",
    }


@router.delete("/{user_id}")
def delete_user(user_id: int) -> dict[str, str]:
    return {
        "status": "ok",
        "message": f"User {user_id} deleted successfully",
    }