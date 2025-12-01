from fastapi import UploadFile
import os

def save_uploaded_file(upload_file: UploadFile, upload_dir: str) -> str:
    if not os.path.exists(upload_dir):
        os.makedirs(upload_dir, exist_ok=True)

    file_location = os.path.join(upload_dir, upload_file.filename)
    with open(file_location, "wb+") as file_object:
        file_object.write(upload_file.file.read())
    
    return file_location

def allowed_file(filename: str, allowed_extensions: set) -> bool:
    return '.' in filename and filename.rsplit('.', 1)[1].lower() in allowed_extensions