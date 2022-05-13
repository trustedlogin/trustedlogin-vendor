import { useMemo } from "react";

export const Notice = ({ heading, link, description, type }) => (
  <div className={`notice notice-success`}>
    <h2>{heading}</h2>
    <h3 className="description">
      <a href={link} rel="noopener" target="_blank">
        {description}
      </a>
    </h3>
  </div>
);

export const Form = ({ children, onSubmit, title }) => {
  return (
    <form onSubmit={onSubmit} title={title}>
      {title ? <h2 className="title">{title}</h2> : null}

      {children}
    </form>
  );
};

export const FormTable = ({ children, title }) => {
  return (
    <>
      {title ? <h2 className="title">{title}</h2> : null}
      <table className="form-table" role="presentation">
        <tbody>{children}</tbody>
      </table>
    </>
  );
};

export const Select = ({ name, label, value, options, help, onChange }) => {
  const attrs = useMemo(() => {
    let a = {
      name,
      label,
      value,
      className: "postform",
    };
    if (help) {
      a["aria-describedby"] = `${name}-description`;
    }
  }, [name, label, value, help]);
  return (
      <FieldTr name={name} label={label} help={help}>
        <select {...attrs} onChange={(e) => onChange(e.target.value)}>
          {options.map(({ value, label }) => (
            <option className="level-0" value={value} key={value}>
              {label}
            </option>
          ))}
        </select>
      </FieldTr>

  );
};

export const FieldTr = ({ children, name, label,help }) => {
	return (
		<tr>
		  <th scope="row">
			<label htmlFor={name}>{label}</label>
				{help ? (
					<span id={`${name}-description`} className="description">
						{help}
					</span>
				) : null}
			</th>
			<td>
				{children}
			</td>
		</tr>
	  );
}

export const Input = ({ name, label, value, type, onChange }) => {
  return (
	  <FieldTr name={name} label={label}>
			<input
				name={name}
				type={type ? type : "text"}
				id={name}
				value={value}
				className="regular-text ltr"
				onChange={(e) => onChange(e.target.value)}
			/>
	  </FieldTr>

  );
};

export const Submit = ({ name, variant, value, disabled, onClick }) => (
  <p className="submit">
    <input
      type="submit"
      name={name}
      id={name}
      className={`button button-${variant ? variant : "secondary"} button-hero`}
      value={value}
      disabled={disabled}
      onClick={onClick}
    />
  </p>
);

export const BigButton = ({ children, variant, onClick }) => (
  <p className="big-button">
    <button
      className={`button button-${variant ? variant : "secondary"} button-hero`}
      onClick={onClick}
    >
      {children}
    </button>
  </p>
);
