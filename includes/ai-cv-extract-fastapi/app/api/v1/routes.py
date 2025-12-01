from fastapi import APIRouter, UploadFile, File, HTTPException
from app.services.chatpdf_service import ChatPDFService
from app.schemas.applicant_schema import ApplicantCreate, ApplicantResponse

router = APIRouter()
chatpdf_service = ChatPDFService()

@router.post("/extract-cv", response_model=ApplicantResponse)
async def extract_cv(file: UploadFile = File(...)):
    if not file.filename.endswith('.pdf'):
        raise HTTPException(status_code=400, detail="Only PDF files are allowed.")
    
    try:
        applicant_data = await chatpdf_service.process_cv(file)
        return applicant_data
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@router.get("/health")
async def health_check():
    return {"status": "healthy"}