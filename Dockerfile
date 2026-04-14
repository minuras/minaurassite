# Static site — nginx:alpine is tiny (~40 MB) and rock-solid.
FROM nginx:alpine

# Remove default nginx landing page
RUN rm -rf /usr/share/nginx/html/*

# Copy site
COPY index.html /usr/share/nginx/html/

# Dokploy's Traefik handles HTTPS termination; nginx listens on 80 internally
EXPOSE 80
