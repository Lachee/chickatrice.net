FROM node:24-slim AS build

RUN corepack enable

WORKDIR /app

COPY package.json pnpm-lock.yaml pnpm-workspace.yaml .npmrc ./
RUN pnpm install --frozen-lockfile

COPY . .
RUN pnpm build
RUN pnpm prune --prod

FROM node:24-slim AS runtime

WORKDIR /app

ENV NODE_ENV=production
ENV PORT=3000
# Mount the config here (see config.example.yaml); startup fails without one.
ENV CONFIG_FILE=/config/config.yaml

COPY --from=build /app/build ./build
COPY --from=build /app/node_modules ./node_modules
COPY --from=build /app/package.json ./package.json
# Migrations the app applies on startup; read relative to WORKDIR.
COPY --from=build /app/drizzle ./drizzle
# Wraps adapter-node's handler so ORIGIN works again; see the file.
COPY --from=build /app/server.js ./server.js

USER node
EXPOSE 3000

CMD ["node", "server.js"]
