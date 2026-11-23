from fastapi import FastAPI

app = FastAPI(title="CNM FastAPI Class A")
    
@app.post("/create-user")
def create_user() -> dict[str, str]:
    return {
        "status": "ok",
        "message": "User created successfully"
    }
from app.routes import users_router

@app.get("/get-user/{user_id}")
def get_user(user_id: int) -> dict[str, str]:
    return {
        "status": "ok",
        "message": f"User {user_id} retrieved successfully"
    }
app = FastAPI(title="CNM FastAPI Class A")

@app.delete("/delete-user/{user_id}")
def delete_user(user_id: int) -> dict[str, str]:
    return {
        "status": "ok",
        "message": f"User {user_id} deleted successfully"
    }
app.include_router(users_router)