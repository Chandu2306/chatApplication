import { MongoClient } from "mongodb";

const client = new MongoClient("mongodb://localhost:27017");
export async function connectDb() {
  await client.connect();
  console.log("db connected");
  return client.db("chat");
}
