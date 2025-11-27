# 🚀 Money Splitwise — Laravel REST API (Docker Setup)

This repository contains the **REST API backend** for the Money Splitwise project.  
The API runs completely inside **Docker containers**, making setup super smooth and platform-independent.

If you’re a **Laravel developer**, feel free to run locally.  
If not, Docker handles everything for you. 🙌

Use:
```bash
php artisan route:list

```
# 🚀 Postman Collection

https://aa0000-2027.postman.co/workspace/Projects~3e7cc139-3d01-42a0-a2ee-3e8e55502a63/collection/37738875-1936276b-949c-499b-bd0f-8bb14e2f03b4?action=share&creator=37738875&active-environment=37738875-fcc723f9-654e-425e-a5cb-eb2fe173810b

# 🚀 Laravel REST API (Docker Setup)

This project is a **Laravel REST API** that runs completely inside **Docker containers** for easy setup and local development.

---

## ⚙️ How to Run the Project

Follow the commands below step-by-step 👇  
(You can copy-paste all of this directly into your terminal.)

```bash
# 1️⃣ Clone the Repository this Repo

# 2️⃣ Copy Environment File and Generate Key
cp .env.example .env
# If Laravel container is not yet running, don’t worry — we’ll generate key after docker up

# 3️⃣ Start the Docker Containers
docker compose up -d

# 4️⃣ Check if Containers are Running
docker ps

# 5️⃣ View Logs (optional)
docker compose logs -f

# 6️⃣ Generate Laravel Application Key (inside container)
docker compose exec app php artisan key:generate

# 7️⃣ Run Database Migrations (optional)
docker compose exec app php artisan migrate

# 8️⃣ Check Application on Browser
# Open this URL in your browser:
# 👉 http://localhost:8000

# 9️⃣ Check API Health Route
# You can check via browser or curl
curl http://localhost:8000/api/v1/health

# 10️⃣ Stop and Remove Containers (When Done)
docker compose down

# 11️⃣ Restart Everything (If Needed)
docker compose down && docker compose up -d

# 12️⃣ Common Docker Commands for Reference
# List running containers
docker ps

# Access Laravel container shell
docker exec -it <container_name> bash

# Restart containers
docker compose restart

# View logs
docker compose logs -f

# Clean unused Docker data
docker system prune -f
```
