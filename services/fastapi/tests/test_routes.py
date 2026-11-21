from fastapi.testclient import TestClient

from app.main import app

client = TestClient(app)


def test_health() -> None:
    response = client.get("/health")
    assert response.status_code == 200
    assert response.json() == {"status": "ok"}


def test_user_routes() -> None:
    cases = (
        ("post", "/create-user", "User created successfully"),
        ("get", "/get-user/42", "User 42 retrieved successfully"),
        ("delete", "/delete-user/42", "User 42 deleted successfully"),
    )
    for method, path, message in cases:
        response = client.request(method, path)
        assert response.status_code == 200
        assert response.json() == {"status": "ok", "message": message}


def test_user_id_must_be_integer() -> None:
    assert client.get("/get-user/invalid").status_code == 422
