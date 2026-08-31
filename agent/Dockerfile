FROM golang:1.23-alpine AS build
WORKDIR /src
COPY . .
RUN go build -o /out/nodexad ./cmd/nodexad
FROM alpine:3.20
RUN apk add --no-cache ca-certificates
COPY --from=build /out/nodexad /usr/local/bin/nodexad
ENTRYPOINT ["nodexad"]
