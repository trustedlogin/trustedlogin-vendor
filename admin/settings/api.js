import apiFetch from "@wordpress/api-fetch";

const path = "/trustedlogin/v1/settings";
export const getSettings = async () => {
  let teams = await apiFetch({ path });
  return { teams };
};

export const updateSettings = async ({ teams }) => {
  teams = await apiFetch({
    path,
    method: "POST",
    data: {
      teams,
    },
  });
  return { teams };
};
