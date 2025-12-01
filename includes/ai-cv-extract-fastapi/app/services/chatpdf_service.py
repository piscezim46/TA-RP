from typing import Dict, Any
import requests
import os

class ChatPDFService:
    def __init__(self):
        self.api_key = os.getenv("CHATPDF_API_KEY")
        self.base_url = "https://api.chatpdf.com/v1"

    def upload_file(self, file_path: str) -> Dict[str, Any]:
        url = f"{self.base_url}/sources/add-file"
        headers = {
            "Authorization": f"Bearer {self.api_key}"
        }
        with open(file_path, 'rb') as file:
            files = {'file': (os.path.basename(file_path), file)}
            response = requests.post(url, headers=headers, files=files)
            return response.json()

    def extract_information(self, source_id: str, questions: list) -> Dict[str, Any]:
        parsed_data = {}
        for question in questions:
            url = f"{self.base_url}/chats/message"
            headers = {
                "Authorization": f"Bearer {self.api_key}",
                "Content-Type": "application/json"
            }
            body = {
                "sourceId": source_id,
                "messages": [{"role": "user", "content": question}]
            }
            response = requests.post(url, headers=headers, json=body)
            data = response.json()
            parsed_data[question] = data.get('content') or data.get('message') or "No response"
        return parsed_data