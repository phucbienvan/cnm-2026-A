import unittest

from fastapi.testclient import TestClient

from app.main import app


class RoutesTest(unittest.TestCase):
    def setUp(self) -> None:
        self.client = TestClient(app)
        self.addCleanup(self.client.close)

    def test_user_endpoints_keep_existing_responses(self) -> None:
        cases = [
            ("POST", "/create-user", "User created successfully"),
            ("GET", "/get-user/7", "User 7 retrieved successfully"),
            ("DELETE", "/delete-user/7", "User 7 deleted successfully"),
        ]

        for method, path, message in cases:
            with self.subTest(method=method, path=path):
                response = self.client.request(method, path)
                self.assertEqual(response.status_code, 200)
                self.assertEqual(response.json(), {"status": "ok", "message": message})

    def test_invalid_user_id_returns_validation_error(self) -> None:
        for method, path in [
            ("GET", "/get-user/not-a-number"),
            ("DELETE", "/delete-user/not-a-number"),
        ]:
            with self.subTest(method=method):
                response = self.client.request(method, path)
                self.assertEqual(response.status_code, 422)
                self.assertEqual(
                    response.json()["detail"][0]["loc"], ["path", "user_id"]
                )

    def test_wrong_method_is_rejected(self) -> None:
        for method, path in [
            ("GET", "/create-user"),
            ("POST", "/get-user/7"),
            ("GET", "/delete-user/7"),
        ]:
            with self.subTest(method=method, path=path):
                self.assertEqual(self.client.request(method, path).status_code, 405)

    def test_healthcheck_returns_ok(self) -> None:
        response = self.client.get("/health")
        self.assertEqual(response.status_code, 200)
        self.assertEqual(response.json(), {"status": "ok"})

    def test_openapi_lists_each_endpoint_once(self) -> None:
        response = self.client.get("/openapi.json")
        self.assertEqual(response.status_code, 200)
        schema = response.json()
        self.assertEqual(schema["info"]["title"], "CNM FastAPI Class A")
        expected = {
            "/create-user": ("post", "users"),
            "/get-user/{user_id}": ("get", "users"),
            "/delete-user/{user_id}": ("delete", "users"),
            "/health": ("get", "health"),
        }
        self.assertEqual(set(schema["paths"]), set(expected))
        operation_ids = []
        for path, (method, tag) in expected.items():
            with self.subTest(path=path):
                self.assertEqual(set(schema["paths"][path]), {method})
                operation = schema["paths"][path][method]
                self.assertEqual(operation["tags"], [tag])
                operation_ids.append(operation["operationId"])
        self.assertEqual(len(operation_ids), len(set(operation_ids)))

    def test_docs_remain_accessible(self) -> None:
        self.assertEqual(self.client.get("/docs").status_code, 200)


if __name__ == "__main__":
    unittest.main()
