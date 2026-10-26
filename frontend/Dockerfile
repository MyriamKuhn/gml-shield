FROM node:24-alpine

WORKDIR /app

# Install the exact dependencies recorded in package-lock.json.
COPY package.json package-lock.json ./
RUN npm ci

# Copy the application source code.
COPY . .

EXPOSE 5173

CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]
