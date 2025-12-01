from fastapi import HTTPException
from app.services.chatpdf_service import ChatPDFService
import pytest

@pytest.fixture
def chatpdf_service():
    return ChatPDFService(api_key="YOUR_CHATPDF_API_KEY")

def test_upload_resume(chatpdf_service):
    # Mock the upload functionality
    response = chatpdf_service.upload_resume("path/to/resume.pdf")
    assert response['success'] is True
    assert 'sourceId' in response

def test_extract_information(chatpdf_service):
    # Mock the extraction functionality
    source_id = "mock_source_id"
    questions = [
        "Full name of the candidate",
        "Email address",
        "Phone number"
    ]
    responses = chatpdf_service.extract_information(source_id, questions)
    assert isinstance(responses, dict)
    assert all(q in responses for q in questions)