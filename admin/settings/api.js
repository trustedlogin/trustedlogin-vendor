import apiFetch from "@wordpress/api-fetch";

const path = "/trustedlogin/v1/settings";
export const getSettings = async () => {
  let settings = await apiFetch({ path });
  if( settings.teams ){
	settings.teams = settings.teams.map( (team,id) => {
	  return {
		  id,
		  ...team
	  };
	});
  }
  return settings;
};

export const updateSettings = async ({ teams, helpscout }) => {
  teams = await apiFetch({
    path,
    method: "POST",
    data: {
      teams,
      helpscout,
    },
  });
  return { teams };
};
