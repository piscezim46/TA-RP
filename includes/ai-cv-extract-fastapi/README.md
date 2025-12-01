# AI CV Extraction FastAPI

This project is a FastAPI application that integrates an AI-powered CV extraction service using the Chat PDF API. It allows users to upload CVs and extracts relevant information such as personal details, education, and skills.

## Project Structure

```
ai-cv-extract-fastapi
├── app
│   ├── main.py                # Entry point of the FastAPI application
│   ├── api
│   │   └── v1
│   │       ├── routes.py      # API routes for CV extraction service
│   │       └── deps.py        # Dependency functions for the API
│   ├── services
│   │   └── chatpdf_service.py  # Logic for interacting with the Chat PDF API
│   ├── models
│   │   └── db_models.py       # Database models for applicant information
│   ├── schemas
│   │   └── applicant_schema.py  # Pydantic schemas for applicant data
│   ├── core
│   │   ├── config.py          # Configuration settings for the application
│   │   └── security.py        # Security-related functions
│   ├── db
│   │   └── database.py        # Database connection and interactions
│   └── utils
│       └── file_utils.py      # Utility functions for file handling
├── tests
│   ├── test_api.py            # Unit tests for API endpoints
│   └── test_services.py       # Unit tests for service layer
├── .env.example                # Example environment variables
├── .gitignore                  # Files to ignore by version control
├── Dockerfile                  # Instructions for building a Docker image
├── requirements.txt            # Python dependencies for the project
├── pyproject.toml             # Project dependencies and configurations
└── README.md                   # Documentation for the project
```

## Setup Instructions

1. **Clone the repository:**
   ```
   git clone <repository-url>
   cd ai-cv-extract-fastapi
   ```

2. **Create a virtual environment:**
   ```
   python -m venv venv
   source venv/bin/activate  # On Windows use `venv\Scripts\activate`
   ```

3. **Install dependencies:**
   ```
   pip install -r requirements.txt
   ```

4. **Set up environment variables:**
   Copy `.env.example` to `.env` and fill in the required values.

5. **Run the application:**
   ```
   uvicorn app.main:app --reload
   ```

## Usage

- **Health Check:** 
  - Endpoint: `GET /api/v1/health`
  - Description: Check if the service is running.

- **CV Extraction:**
  - Endpoint: `POST /api/v1/extract`
  - Description: Upload a CV file to extract information.

## Testing

To run the tests, use the following command:
```
pytest
```

## License

This project is licensed under the MIT License.