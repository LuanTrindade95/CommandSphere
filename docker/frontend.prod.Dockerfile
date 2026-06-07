FROM node:22-alpine AS deps
WORKDIR /app
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci

FROM deps AS build
COPY frontend .
RUN npm run build

FROM node:22-alpine AS runtime
WORKDIR /app
ENV NODE_ENV=production
ENV PORT=4000
COPY --from=build /app/dist/frontend ./dist/frontend
COPY --from=deps /app/node_modules ./node_modules
COPY frontend/package.json ./
EXPOSE 4000
CMD ["node", "dist/frontend/server/server.mjs"]
