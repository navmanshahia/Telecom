"use client";

import { Float, Line, Sparkles } from "@react-three/drei";
import { Canvas, useFrame } from "@react-three/fiber";
import { useMemo, useRef } from "react";
import * as THREE from "three";

function NetworkCore() {
  const group = useRef<THREE.Group>(null);
  const inner = useRef<THREE.Mesh>(null);

  const nodes = useMemo(
    () => [
      new THREE.Vector3(-2.25, 0.8, 0.3),
      new THREE.Vector3(-1.35, -1.45, -0.5),
      new THREE.Vector3(0.1, 1.65, -0.7),
      new THREE.Vector3(1.65, 0.85, 0.5),
      new THREE.Vector3(2.1, -1.05, -0.25),
      new THREE.Vector3(0.55, -1.8, 0.7),
    ],
    []
  );

  useFrame((state, delta) => {
    if (group.current) {
      group.current.rotation.y += delta * 0.09;
      group.current.rotation.x = Math.sin(state.clock.elapsedTime * 0.22) * 0.08;
    }
    if (inner.current) {
      const s = 1 + Math.sin(state.clock.elapsedTime * 1.45) * 0.035;
      inner.current.scale.setScalar(s);
    }
  });

  return (
    <group ref={group}>
      <mesh rotation={[0.2,0.5,0]} scale={3.25}>
        <torusKnotGeometry args={[1.05,0.004,180,20,2,5]} />
        <meshBasicMaterial color="#47dfff" transparent opacity={0.12} />
      </mesh>
      <Float speed={1.25} rotationIntensity={0.35} floatIntensity={0.55}>
        <mesh ref={inner}>
          <icosahedronGeometry args={[1.18, 5]} />
          <meshPhysicalMaterial
            color="#07182a"
            emissive="#082642"
            emissiveIntensity={0.9}
            roughness={0.18}
            metalness={0.28}
            transmission={0.28}
            thickness={0.8}
            transparent
            opacity={0.94}
          />
        </mesh>

        <mesh scale={1.23}>
          <icosahedronGeometry args={[1.18, 2]} />
          <meshBasicMaterial
            color="#54e8ff"
            wireframe
            transparent
            opacity={0.24}
          />
        </mesh>

        <mesh rotation={[Math.PI / 2.35, 0.15, 0]}>
          <torusGeometry args={[1.78, 0.012, 16, 160]} />
          <meshBasicMaterial color="#6cf2ff" transparent opacity={0.72} />
        </mesh>

        <mesh rotation={[0.65, -0.25, Math.PI / 2]}>
          <torusGeometry args={[2.18, 0.008, 16, 160]} />
          <meshBasicMaterial color="#8b7cff" transparent opacity={0.42} />
        </mesh>

        {nodes.map((node, index) => (
          <group key={index} position={node}>
            <mesh>
              <sphereGeometry args={[0.055, 20, 20]} />
              <meshBasicMaterial color={index % 2 ? "#a88cff" : "#6cf2ff"} />
            </mesh>
            <mesh scale={2.7}>
              <sphereGeometry args={[0.055, 20, 20]} />
              <meshBasicMaterial
                color={index % 2 ? "#a88cff" : "#6cf2ff"}
                transparent
                opacity={0.12}
              />
            </mesh>
          </group>
        ))}

        {nodes.map((node, index) => (
          <Line
            key={"line-" + index}
            points={[new THREE.Vector3(0, 0, 0), node]}
            color={index % 2 ? "#806dff" : "#36dcff"}
            transparent
            opacity={0.45}
            lineWidth={0.7}
          />
        ))}
      </Float>

      <Sparkles
        count={72}
        scale={[6.5, 5, 4]}
        size={1.25}
        speed={0.28}
        opacity={0.5}
        color="#75e9ff"
      />
    </group>
  );
}

export default function HeroScene() {
  return (
    <div className="hero-canvas" aria-hidden="true">
      <Canvas
        dpr={[1, 1.5]}
        camera={{ position: [0, 0, 6.4], fov: 42 }}
        gl={{ antialias: true, alpha: true, powerPreference: "high-performance" }}
      >
        <ambientLight intensity={0.5} />
        <pointLight position={[4, 5, 5]} intensity={14} color="#7be8ff" />
        <pointLight position={[-4, -3, 2]} intensity={9} color="#725cff" />
        <fog attach="fog" args={["#02070c",7,15]} />
        <NetworkCore />
      </Canvas>
    </div>
  );
}
