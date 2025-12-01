import os
from dotenv import load_dotenv

load_dotenv()

class Config:
    API_KEY: str = os.getenv("CHATPDF_API_KEY")
    DATABASE_URL: str = os.getenv("DATABASE_URL")
    DEBUG: bool = os.getenv("DEBUG", "False").lower() in ("true", "1", "t")
    
config = Config()