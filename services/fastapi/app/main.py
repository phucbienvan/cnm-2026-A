from fastapi import FastAPI

app = FastAPI(title="CNM FastAPI")


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}
