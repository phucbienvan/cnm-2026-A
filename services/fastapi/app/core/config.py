import os
from dotenv import load_dotenv

load_dotenv()

class Setting:
    DATABASE_URL : str = os.getenv("DATABASE_URL")

settings = Setting()
