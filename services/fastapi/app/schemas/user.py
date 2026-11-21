from pydantic import BaseModel

class UserResponse(BaseModel):
    status: str
    message: str

