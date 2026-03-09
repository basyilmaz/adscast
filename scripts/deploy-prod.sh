#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
COMPOSE_FILE="${ROOT_DIR}/docker-compose.prod.yml"
ENV_FILE="${ROOT_DIR}/.env.production"

if [[ ! -f "${ENV_FILE}" ]]; then
  echo ".env.production bulunamadi. Once .env.production.example dosyasini kopyalayip doldurun."
  exit 1
fi

if [[ ! -f "${COMPOSE_FILE}" ]]; then
  echo "docker-compose.prod.yml bulunamadi."
  exit 1
fi

cd "${ROOT_DIR}"

docker compose --env-file "${ENV_FILE}" -f "${COMPOSE_FILE}" up -d --build

echo "Deployment tamamlandi. Servis durumlari:"
docker compose --env-file "${ENV_FILE}" -f "${COMPOSE_FILE}" ps
