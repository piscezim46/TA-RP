from fastapi.testclient import TestClient
from app.main import app

client = TestClient(app)

def test_health_check():
    response = client.get("/api/v1/health")
    assert response.status_code == 200
    assert response.json() == {"status": "healthy"}

def test_extract_cv():
    with open("tests/test_resume.pdf", "rb") as resume_file:
        response = client.post("/api/v1/extract-cv", files={"resume": resume_file})
    assert response.status_code == 200
    assert "applicant_id" in response.json()
    assert "parsed" in response.json()