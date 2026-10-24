# syntax=docker/dockerfile:1

##########
# Stage 1 — Build du SPA (React + Vite + TypeScript)
##########
FROM node:22-alpine AS build
WORKDIR /app

COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci

COPY frontend/ ./

# URL de base de l'API vue par le navigateur.
#   /api/v1                  → same-origin (le nginx du frontend proxy /api vers l'API)
#   https://api.<domain>/v1  → appel direct au sous-domaine API (CORS côté API)
ARG VITE_API_BASE_URL=/api/v1
ENV VITE_API_BASE_URL=${VITE_API_BASE_URL}

RUN npm run build

##########
# Stage 2 — Service statique (nginx : SPA + proxy /api)
##########
FROM nginx:1.31-alpine

# L'image officielle peut précéder la publication de correctifs Alpine.
# Met à niveau les paquets du runtime avant le contrôle Trivy.
RUN apk upgrade --no-cache

COPY docker/frontend/proxy-scheme.conf /etc/nginx/conf.d/00-proxy-scheme.conf
COPY docker/frontend/default.conf.template /etc/nginx/templates/default.conf.template
COPY --from=build /app/dist /usr/share/nginx/html

EXPOSE 80
