import { createHashRouter, Navigate } from "react-router-dom";
import Layout from "./components/Layout";
import { lazy } from "react";

// One chunk per page (the Dashboard pulls in the chart library, which no other page needs).
// Vite loads the chunks relative to the main script, so no extra PHP enqueue is needed.
const Dashboard = lazy(() => import("./pages/Dashboard"));
const Settings = lazy(() => import("./pages/Settings"));
const Knowledge = lazy(() => import("./pages/Knowledge"));
const Conversations = lazy(() => import("./pages/Conversations"));
const Insights = lazy(() => import("./pages/Insights"));
const Requests = lazy(() => import("./pages/Requests"));
const License = lazy(() => import("./pages/License"));
const Setup = lazy(() => import("./pages/Setup"));

export const router = createHashRouter([
  {
    path: "/",
    element: <Layout />,
    children: [
      { index: true, element: <Navigate to="/dashboard" replace /> },
      { path: "dashboard", element: <Dashboard /> },
      { path: "settings", element: <Settings /> },
      { path: "settings/:tab", element: <Settings /> },
      { path: "knowledge", element: <Knowledge /> },
      { path: "conversations", element: <Conversations /> },
      { path: "conversations/:id", element: <Conversations /> },
      { path: "insights", element: <Insights /> },
      { path: "requests", element: <Requests /> },
      { path: "license", element: <License /> },
      { path: "setup", element: <Setup /> },
      { path: "*", element: <Navigate to="/dashboard" replace /> },
    ],
  },
]);
