from sqlalchemy import Column, Integer, String, Text
from sqlalchemy.ext.declarative import declarative_base

Base = declarative_base()

class Applicant(Base):
    __tablename__ = 'applicants'

    id = Column(Integer, primary_key=True, index=True)
    ticket_id = Column(Integer, index=True)
    full_name = Column(String(255))
    email = Column(String(255))
    phone = Column(String(50))
    linkedin = Column(String(255))
    degree = Column(String(255))
    age = Column(String(50))
    gender = Column(String(50))
    nationality = Column(String(100))
    years_experience = Column(String(50))
    skills = Column(Text)
    resume_file = Column(String(255))
    ai_summary = Column(Text)
    parsing_status = Column(String(50))
    created_at = Column(String(50))  # Consider using DateTime for better handling of timestamps