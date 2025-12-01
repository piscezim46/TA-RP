from pydantic import BaseModel
from typing import Optional

class ApplicantCreate(BaseModel):
    role_applied: str
    department: str
    position_id: Optional[int] = None
    assigned_to: Optional[int] = None
    note: Optional[str] = None
    resume_file: str

class ApplicantResponse(BaseModel):
    id: int
    full_name: str
    email: str
    phone: str
    linkedin: Optional[str] = None
    degree: Optional[str] = None
    age: Optional[str] = None
    gender: Optional[str] = None
    nationality: Optional[str] = None
    years_experience: Optional[str] = None
    skills: Optional[str] = None
    resume_file: str
    ai_summary: dict
    parsing_status: str

    class Config:
        orm_mode = True