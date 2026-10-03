FROM mcr.microsoft.com/playwright:v1.55.0-noble

WORKDIR /app

COPY package.json ./package.json
RUN npm install --omit=dev

COPY server.mjs ./server.mjs
COPY e2e ./e2e

CMD ["node", "server.mjs"]
