import logo from "../../assets/syriatel-logo.webp";

export default function Logo({ height = 48, className = "" }) {
  return (
    <img
      src={logo}
      alt="Syriatel — سيريتل"
      height={height}
      className={className}
      style={{
        display: "block",
        height: `${height}px`,
        width: "fit-content",
        objectFit: "contain",
      }}
    />
  );
}
