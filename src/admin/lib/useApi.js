import { useCallback, useEffect, useState } from "react";
import { api } from "./api";

/** Load data from an endpoint with loading / error state and a reload(). */
export function useApi(path, deps = []) {
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);
  const [loading, setLoading] = useState(true);

  const reload = useCallback(() => {
    setLoading(true);
    return api(path)
      .then((d) => {
        setData(d);
        setError(null);
        return d;
      })
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  }, [path]);

  useEffect(() => {
    reload();
  }, [reload, ...deps]);

  return { data, setData, error, loading, reload };
}
