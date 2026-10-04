#version 450
#extension GL_GOOGLE_include_directive : require

#include "level.glsl"
#include "quad.glsl"

struct Wall
{
    float x1;
    float y1;
    float x2;
    float y2;
    uint frontSector;
    uint backSector;
    uint type;
    uint picture;
    float offsetX;
    float offsetY;
    float span;
    uint flags;
    int lightBias;
    uint padding0;
    uint padding1;
    uint padding2;
};

layout(std430, set = 0, binding = 3) readonly buffer Walls
{
    Wall walls[];
};

// The picture of every wall part, it follows the textures of the sidedefs (a switch changes its texture).
layout(std430, set = 0, binding = 7) readonly buffer WallPictures
{
    uint wallPictures[];
};

layout(location = 0) out vec2 outUv;
layout(location = 1) flat out uint outPicture;
layout(location = 2) flat out uint outLight;
layout(location = 3) flat out uint outFlags;
layout(location = 4) out vec3 outWorld;
layout(location = 5) flat out vec3 outNormal;

void main()
{
    Wall wall = walls[gl_InstanceIndex];
    vec2 corner = CORNERS[gl_VertexIndex];

    vec2 start = vec2(wall.x1, wall.y1);
    vec2 direction = vec2(wall.x2, wall.y2) - start;

    // A seg is seen from its right side only.
    if (direction.x * (frame.eye.y - start.y) - direction.y * (frame.eye.x - start.x) > 0.0)
    {
        gl_Position = vec4(2.0, 2.0, 2.0, 1.0);
        outUv = vec2(0.0);
        outPicture = 0u;
        outLight = 0u;
        outFlags = 0u;
        outWorld = vec3(0.0);
        outNormal = vec3(0.0);

        return;
    }

    Sector front = sectors[wall.frontSector];
    uint picture = wallPictures[gl_InstanceIndex];
    float height = float(rects[picture].w);
    bool unpegged = (wall.flags & WALL_UNPEGGED) != 0u;

    float bottom = front.floorHeight;
    float top = front.ceilingHeight;
    float anchor = top;

    if (wall.backSector == NO_SECTOR)
    {
        if (unpegged)
        {
            anchor = bottom + height;
        }
    }
    else
    {
        Sector back = sectors[wall.backSector];

        if (wall.type == WALL_UPPER)
        {
            bottom = back.ceilingHeight;

            bool bothSky = (front.flags & SECTOR_CEILING_IS_SKY) != 0u && (back.flags & SECTOR_CEILING_IS_SKY) != 0u;

            if (bottom >= top || bothSky)
            {
                top = bottom;
            }

            anchor = unpegged ? top : back.ceilingHeight + height;
        }
        else if (wall.type == WALL_LOWER)
        {
            top = back.floorHeight;

            if (top <= bottom)
            {
                top = bottom;
            }

            anchor = unpegged ? front.ceilingHeight : back.floorHeight;
        }
        else
        {
            bottom = max(front.floorHeight, back.floorHeight);
            top = max(bottom, min(front.ceilingHeight, back.ceilingHeight));
            anchor = unpegged ? bottom + height : top;
        }
    }

    float z = mix(bottom, top, corner.y);
    vec2 position = start + direction * corner.x;

    gl_Position = frame.viewProjection * vec4(position, z, 1.0);

    float scroll = 0.0;

    if ((wall.flags & WALL_SCROLLING) != 0u)
    {
        // One texel per tic, 35 tics per second.
        scroll = frame.eye.w * TICS_PER_SECOND;
    }

    outUv = vec2(wall.offsetX + corner.x * wall.span + scroll, anchor - z + wall.offsetY);
    outPicture = picture;
    outLight = uint(clamp(int(front.light >> 4) + wall.lightBias, 0, 15)) << 4;
    outFlags = wall.flags;
    outWorld = vec3(position, z);
    outNormal = vec3(normalize(vec2(direction.y, -direction.x)), 0.0);
}
